<?php

namespace App\Services;

use App\Models\BoardingPassVerification;
use App\Models\GatePassSnapshot;
use App\Models\GateVerificationOutbox;
use App\Models\VerificationRecord;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class GateSyncService
{
    public function __construct(private GateSyncSignature $signature) {}

    /**
     * @return array<string, mixed>
     */
    public function synchronize(): array
    {
        if (! config('services.gate_terminal.enabled')) {
            return [
                'enabled' => false,
                'connected' => false,
                'message' => 'This application is not configured as a local gate terminal.',
            ];
        }

        $this->saveState('sync_status', 'SYNCING');
        $this->saveState('last_error', null);
        $snapshotsDownloaded = 0;
        $eventsSynchronized = 0;

        try {
            $snapshotsDownloaded = $this->downloadSnapshots();
            $eventsSynchronized = $this->sendOutbox();
            $this->saveState('connection_status', 'ONLINE');
            $this->saveState('last_synced_at', now()->toIso8601String());
            $this->saveState('sync_status', 'SYNCED');
        } catch (ConnectionException|RequestException $exception) {
            $this->markEligibleOutboxAsFailed($exception->getMessage());
            $this->saveState('connection_status', 'OFFLINE');
            $this->saveState('sync_status', 'FAILED');
            $this->saveState('last_error', Str::limit($exception->getMessage(), 1000));
        } catch (\UnexpectedValueException $exception) {
            $this->saveState('sync_status', 'FAILED');
            $this->saveState('last_error', Str::limit($exception->getMessage(), 1000));
        }

        return [
            'enabled' => true,
            'connected' => $this->state('connection_status') === 'ONLINE',
            'sync_status' => $this->state('sync_status'),
            'snapshots_downloaded' => $snapshotsDownloaded,
            'events_synchronized' => $eventsSynchronized,
            'last_synced_at' => $this->state('last_synced_at'),
            'last_error' => $this->state('last_error'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function closeout(): array
    {
        if (! config('services.gate_terminal.enabled')) {
            return ['closed' => false, 'message' => 'Local gate terminal mode is not enabled.'];
        }

        DB::connection('gate_terminal')->table('gate_pass_snapshots')
            ->where('is_enabled', true)
            ->update(['is_enabled' => false, 'updated_at' => now()]);

        try {
            $this->sendOutbox(includeUnapprovedScans: true);
        } catch (ConnectionException|RequestException $exception) {
            $this->saveState('last_error', Str::limit($exception->getMessage(), 1000));

            return [
                'closed' => false,
                'message' => 'Offline events could not be synchronized. Local verification remains disabled.',
            ];
        }
        $unsynchronizedCount = GateVerificationOutbox::query()
            ->whereIn('sync_status', [
                GateVerificationOutbox::SYNC_PENDING,
                GateVerificationOutbox::SYNC_SYNCING,
                GateVerificationOutbox::SYNC_FAILED,
            ])
            ->count();

        if ($unsynchronizedCount > 0) {
            return [
                'closed' => false,
                'message' => 'Offline events must synchronize before this terminal can release its gate reservations.',
                'unsynchronized' => $unsynchronizedCount,
            ];
        }

        $leaseIds = DB::connection('gate_terminal')->table('gate_pass_snapshots')
            ->whereNotNull('lease_id')
            ->distinct()
            ->pluck('lease_id')
            ->all();

        if ($leaseIds === []) {
            return ['closed' => true, 'released' => 0];
        }

        $released = 0;
        try {
            foreach (array_chunk($leaseIds, 2000) as $leaseIdChunk) {
                $response = $this->request()
                    ->post('/api/gate/v1/closeout', ['lease_ids' => $leaseIdChunk])
                    ->throw();
                $released += (int) $response->json('released', 0);
            }
        } catch (ConnectionException|RequestException $exception) {
            $this->saveState('last_error', Str::limit($exception->getMessage(), 1000));

            return [
                'closed' => false,
                'message' => 'Central closeout failed. Local verification remains disabled until synchronization succeeds.',
            ];
        }

        GatePassSnapshot::query()->delete();

        return ['closed' => true, 'released' => $released];
    }

    /**
     * @return array<string, mixed>
     */
    public function status(): array
    {
        if (! config('services.gate_terminal.enabled')) {
            return [
                'enabled' => false,
                'terminal_id' => null,
                'connection_status' => 'DISABLED',
                'sync_status' => 'DISABLED',
                'last_synced_at' => null,
                'last_error' => null,
                'cached_passes' => 0,
                'pending' => 0,
                'syncing' => 0,
                'failed' => 0,
                'conflict' => 0,
            ];
        }

        $counts = GateVerificationOutbox::query()
            ->selectRaw('sync_status, COUNT(*) as count')
            ->groupBy('sync_status')
            ->pluck('count', 'sync_status')
            ->all();

        return [
            'enabled' => (bool) config('services.gate_terminal.enabled'),
            'terminal_id' => config('services.gate_terminal.id'),
            'connection_status' => $this->state('connection_status') ?? 'UNKNOWN',
            'sync_status' => $this->state('sync_status') ?? 'NOT_SYNCED',
            'last_synced_at' => $this->state('last_synced_at'),
            'last_error' => $this->state('last_error'),
            'cached_passes' => GatePassSnapshot::query()->where('is_enabled', true)->count(),
            'pending' => (int) ($counts[GateVerificationOutbox::SYNC_PENDING] ?? 0),
            'syncing' => (int) ($counts[GateVerificationOutbox::SYNC_SYNCING] ?? 0),
            'failed' => (int) ($counts[GateVerificationOutbox::SYNC_FAILED] ?? 0),
            'conflict' => (int) ($counts[GateVerificationOutbox::SYNC_CONFLICT] ?? 0),
        ];
    }

    private function downloadSnapshots(): int
    {
        $secret = (string) config('services.gate_terminal.token');
        $afterId = 0;
        $downloaded = 0;
        $pageSize = max(1, min(1000, (int) config('services.gate_terminal.snapshot_batch_size', 1000)));

        do {
            $response = $this->request()
                ->get('/api/gate/v1/snapshot', [
                    'after_id' => $afterId,
                    'per_page' => $pageSize,
                ])
                ->throw();
            $expectedTerminalId = config('services.gate_terminal.id');

            if ($response->json('terminal_id') !== $expectedTerminalId) {
                throw new \UnexpectedValueException('The central server returned a snapshot for another gate terminal.');
            }

            $snapshots = $response->json('snapshots');
            if (! is_array($snapshots)) {
                throw new \UnexpectedValueException('The central server returned an invalid gate snapshot list.');
            }
            $revokedLeaseIds = $response->json('revoked_lease_ids');
            $revocationsSignature = $response->json('revocations_signature');

            if (
                ! is_array($revokedLeaseIds)
                || ! is_string($revocationsSignature)
                || ! $this->signature->revocationsAreValid($revokedLeaseIds, $revocationsSignature, $secret)
            ) {
                throw new \UnexpectedValueException('The central server returned invalid gate reservation updates.');
            }

            DB::connection('gate_terminal')->transaction(function () use ($snapshots, $revokedLeaseIds, $secret): void {
                foreach ($snapshots as $snapshot) {
                    if (
                        ! is_array($snapshot)
                        || ! isset($snapshot['snapshot_signature'])
                        || ! $this->signature->snapshotIsValid(
                            $snapshot,
                            (string) $snapshot['snapshot_signature'],
                            $secret,
                        )
                    ) {
                        throw new \UnexpectedValueException('The central server returned an invalid gate snapshot.');
                    }

                    GatePassSnapshot::query()->updateOrCreate(
                        ['pass_reference' => $snapshot['pass_reference']],
                        [...$snapshot, 'is_enabled' => true],
                    );
                }

                if ($revokedLeaseIds !== []) {
                    GatePassSnapshot::query()
                        ->whereIn('lease_id', $revokedLeaseIds)
                        ->update(['is_enabled' => false, 'updated_at' => now()]);
                }
            });

            $downloaded += count($snapshots);
            $nextCursor = $response->json('next_cursor');

            if ($nextCursor === null) {
                break;
            }

            if (! is_numeric($nextCursor) || (int) $nextCursor <= $afterId) {
                throw new \UnexpectedValueException('The central server returned an invalid gate snapshot cursor.');
            }

            $afterId = (int) $nextCursor;
        } while (true);

        return $downloaded;
    }

    private function sendOutbox(bool $includeUnapprovedScans = false): int
    {
        $batchSize = max(1, min(250, (int) config('services.gate_terminal.sync_batch_size', 100)));
        $eventsSynchronized = 0;

        GateVerificationOutbox::query()
            ->whereIn('sync_status', [
                GateVerificationOutbox::SYNC_PENDING,
                GateVerificationOutbox::SYNC_FAILED,
            ])
            ->where(function ($query) use ($includeUnapprovedScans): void {
                if (! $includeUnapprovedScans) {
                    $query->where('result', '!=', BoardingPassVerification::STATUS_VALID)
                        ->orWhereNotNull('approved_at');
                }
            })
            ->where(function ($query): void {
                $query->whereNull('next_attempt_at')
                    ->orWhere('next_attempt_at', '<=', now());
            })
            ->orderBy('id')
            ->limit($batchSize)
            ->get()
            ->each(function (GateVerificationOutbox $outbox) use (&$eventsSynchronized): void {
                $outbox->update([
                    'sync_status' => GateVerificationOutbox::SYNC_SYNCING,
                    'sync_attempts' => $outbox->sync_attempts + 1,
                ]);
                $event = $this->eventPayload($outbox);
                $event['event_signature'] = $this->signature->signEvent(
                    $event,
                    (string) config('services.gate_terminal.token'),
                );

                try {
                    $response = $this->request('/api/gate/v1/synchronize')
                        ->post('/api/gate/v1/synchronize', ['events' => [$event]])
                        ->throw();
                } catch (ConnectionException|RequestException $exception) {
                    $this->markFailed($outbox, $exception->getMessage());

                    throw $exception;
                }

                $result = collect($response->json('results', []))
                    ->firstWhere('idempotency_key', $outbox->idempotency_key);

                if (! is_array($result) || ! in_array($result['status'] ?? null, [
                    VerificationRecord::SYNC_SYNCED,
                    VerificationRecord::SYNC_CONFLICT,
                ], true)) {
                    $this->markFailed($outbox, 'Central sync response omitted this idempotency key.');

                    return;
                }

                $outbox->update([
                    'sync_status' => $result['status'],
                    'server_verification_record_id' => $result['verification_record_id'] ?? null,
                    'last_sync_error' => $result['reason'] ?? null,
                    'next_attempt_at' => null,
                ]);
                $eventsSynchronized++;
            });

        return $eventsSynchronized;
    }

    /**
     * @return array<string, mixed>
     */
    private function eventPayload(GateVerificationOutbox $outbox): array
    {
        return [
            'idempotency_key' => $outbox->idempotency_key,
            'boarding_pass_id' => $outbox->boarding_pass_id,
            'flight_id' => $outbox->flight_id,
            'passenger_id' => $outbox->passenger_id,
            'staff_id' => $outbox->staff_id,
            'lease_id' => $outbox->lease_id,
            'verification_reference' => $outbox->verification_reference,
            'result' => $outbox->result,
            'verification_method' => $outbox->verification_method,
            'gate' => $outbox->gate,
            'device_identifier' => $outbox->device_identifier,
            'verified_at' => $outbox->verified_at->toIso8601String(),
            'notes' => $outbox->notes,
            'approved_at' => $outbox->approved_at?->toIso8601String(),
        ];
    }

    private function request(): PendingRequest
    {
        $baseUrl = rtrim((string) config('services.gate_terminal.central_url'), '/');
        $token = (string) config('services.gate_terminal.token');

        if ($baseUrl === '' || $token === '') {
            throw new \RuntimeException('Gate terminal central URL and API token must be configured.');
        }

        return Http::withToken($token)
            ->acceptJson()
            ->connectTimeout(3)
            ->timeout(10)
            ->baseUrl($baseUrl);
    }

    private function markEligibleOutboxAsFailed(string $error): void
    {
        GateVerificationOutbox::query()
            ->whereIn('sync_status', [
                GateVerificationOutbox::SYNC_PENDING,
                GateVerificationOutbox::SYNC_SYNCING,
            ])
            ->each(fn (GateVerificationOutbox $outbox) => $this->markFailed($outbox, $error));
    }

    private function markFailed(GateVerificationOutbox $outbox, string $error): void
    {
        $delayMinutes = min(60, 2 ** min(6, (int) $outbox->sync_attempts));
        $outbox->update([
            'sync_status' => GateVerificationOutbox::SYNC_FAILED,
            'next_attempt_at' => now()->addMinutes($delayMinutes),
            'last_sync_error' => Str::limit($error, 1000),
        ]);
    }

    private function saveState(string $key, ?string $value): void
    {
        DB::connection('gate_terminal')->table('gate_sync_state')->updateOrInsert(
            ['key' => $key],
            ['value' => $value, 'updated_at' => now()],
        );
    }

    private function state(string $key): ?string
    {
        return DB::connection('gate_terminal')->table('gate_sync_state')
            ->where('key', $key)
            ->value('value');
    }
}
