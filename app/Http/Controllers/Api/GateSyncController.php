<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\BoardingPass;
use App\Models\BoardingPassOfflineLease;
use App\Models\BoardingPassVerification;
use App\Models\Booking;
use App\Models\CheckIn;
use App\Models\Flight;
use App\Models\GateTerminal;
use App\Models\User;
use App\Models\VerificationRecord;
use App\Services\AuditLogService;
use App\Services\GateSyncSignature;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class GateSyncController extends Controller
{
    public function snapshot(Request $request, GateSyncSignature $signature): JsonResponse
    {
        $terminal = $this->terminal($request);
        $offlineWindow = max(15, min(1440, (int) config('services.gate_terminal.offline_window_minutes', 240)));
        $afterId = max(0, $request->integer('after_id'));
        $pageSize = max(1, min(1000, $request->integer('per_page', 1000)));
        $revokedLeaseIds = BoardingPassOfflineLease::query()
            ->whereBelongsTo($terminal, 'gateTerminal')
            ->whereIn('status', [
                BoardingPassOfflineLease::STATUS_ACTIVE,
                BoardingPassOfflineLease::STATUS_CONSUMED,
            ])
            ->whereDoesntHave('boardingPass', function ($query): void {
                $query->where('pass_status', BoardingPass::STATUS_ACTIVE)
                    ->where('expires_at', '>', now())
                    ->whereHas('booking', fn ($booking) => $booking->where('booking_status', Booking::STATUS_CONFIRMED))
                    ->whereHas('flight', function ($flight): void {
                        $flight->whereIn('status', [Flight::STATUS_SCHEDULED, Flight::STATUS_BOARDING])
                            ->whereDate('departure_date', '>=', today());
                    })
                    ->whereHas('checkIn', fn ($checkIn) => $checkIn->where('status', CheckIn::STATUS_CHECKED_IN));
            })
            ->pluck('lease_id')
            ->all();
        $candidates = BoardingPass::query()
            ->with(['booking', 'flight', 'checkIn', 'passenger'])
            ->where('pass_status', BoardingPass::STATUS_ACTIVE)
            ->where('id', '>', $afterId)
            ->where('expires_at', '>', now())
            ->whereHas('booking', fn ($query) => $query->where('booking_status', Booking::STATUS_CONFIRMED))
            ->whereHas('flight', function ($query): void {
                $query->whereIn('status', [Flight::STATUS_SCHEDULED, Flight::STATUS_BOARDING])
                    ->whereDate('departure_date', '>=', today());
            })
            ->whereHas('checkIn', fn ($query) => $query->where('status', CheckIn::STATUS_CHECKED_IN))
            ->orderBy('id')
            ->limit($pageSize + 1)
            ->get();
        $hasMore = $candidates->count() > $pageSize;
        if ($hasMore) {
            $candidates = $candidates->take($pageSize)->values();
        }
        $nextCursor = $hasMore ? (int) $candidates->last()->getKey() : null;

        $snapshots = DB::transaction(function () use ($candidates, $terminal, $offlineWindow, $signature): array {
            $snapshots = [];

            foreach ($candidates as $candidate) {
                $boardingPass = BoardingPass::query()
                    ->whereKey($candidate->getKey())
                    ->lockForUpdate()
                    ->first();

                if ($boardingPass === null || $boardingPass->pass_status !== BoardingPass::STATUS_ACTIVE) {
                    continue;
                }

                $lease = BoardingPassOfflineLease::query()
                    ->where('boarding_pass_id', $boardingPass->getKey())
                    ->lockForUpdate()
                    ->first();

                if (
                    $lease !== null
                    && $lease->status === BoardingPassOfflineLease::STATUS_ACTIVE
                    && $lease->gate_terminal_id !== $terminal->getKey()
                ) {
                    continue;
                }

                $validUntil = min(
                    now()->addMinutes($offlineWindow),
                    $boardingPass->expires_at,
                );
                if ($lease === null) {
                    $lease = BoardingPassOfflineLease::create([
                        'boarding_pass_id' => $boardingPass->getKey(),
                        'gate_terminal_id' => $terminal->getKey(),
                        'lease_id' => (string) Str::uuid(),
                        'status' => BoardingPassOfflineLease::STATUS_ACTIVE,
                        'offline_valid_until' => $validUntil,
                    ]);
                } elseif ($lease->status !== BoardingPassOfflineLease::STATUS_ACTIVE) {
                    $lease->update([
                        'gate_terminal_id' => $terminal->getKey(),
                        'lease_id' => (string) Str::uuid(),
                        'status' => BoardingPassOfflineLease::STATUS_ACTIVE,
                        'offline_valid_until' => $validUntil,
                        'consumed_at' => null,
                        'released_at' => null,
                    ]);
                } else {
                    $lease->update(['offline_valid_until' => $validUntil]);
                }

                $boardingPass->loadMissing(['booking', 'flight', 'passenger']);
                $snapshot = [
                    'boarding_pass_id' => (int) $boardingPass->getKey(),
                    'pass_reference' => $boardingPass->pass_reference,
                    'passenger_name' => $boardingPass->passenger->name,
                    'verification_token_hash' => $boardingPass->verification_token_hash,
                    'pass_status' => $boardingPass->pass_status,
                    'booking_status' => $boardingPass->booking->booking_status,
                    'flight_id' => (int) $boardingPass->flight_id,
                    'flight_status' => $boardingPass->flight->status,
                    'flight_deleted' => $boardingPass->flight->trashed(),
                    'flight_number' => $boardingPass->flight->flight_number,
                    'destination' => $boardingPass->flight->arrival_airport,
                    'seat_number' => $boardingPass->seat_number,
                    'departure_at' => $boardingPass->flight->departure_date
                        ->copy()
                        ->setTimeFromTimeString($boardingPass->flight->departure_time)
                        ->format('Y-m-d H:i:s'),
                    'expires_at' => $boardingPass->expires_at->format('Y-m-d H:i:s'),
                    'lease_id' => $lease->lease_id,
                    'offline_valid_until' => $lease->offline_valid_until->format('Y-m-d H:i:s'),
                ];
                $snapshot['snapshot_signature'] = $signature->signSnapshot($snapshot, $terminal->api_token);
                $snapshots[] = $snapshot;
            }

            return $snapshots;
        }, attempts: 3);

        return response()->json([
            'terminal_id' => $terminal->terminal_id,
            'generated_at' => now()->toIso8601String(),
            'offline_window_minutes' => $offlineWindow,
            'next_cursor' => $nextCursor,
            'revoked_lease_ids' => $revokedLeaseIds,
            'revocations_signature' => $signature->signRevocations($revokedLeaseIds, $terminal->api_token),
            'snapshots' => $snapshots,
        ]);
    }

    public function synchronize(
        Request $request,
        GateSyncSignature $signature,
        AuditLogService $auditLogService,
    ): JsonResponse {
        $terminal = $this->terminal($request);
        $validated = $request->validate([
            'events' => ['required', 'array', 'min:1', 'max:250'],
            'events.*.idempotency_key' => ['required', 'uuid'],
            'events.*.boarding_pass_id' => ['nullable', 'integer'],
            'events.*.flight_id' => ['nullable', 'integer'],
            'events.*.passenger_id' => ['nullable', 'integer'],
            'events.*.staff_id' => ['required', 'integer', 'exists:users,id'],
            'events.*.lease_id' => ['nullable', 'uuid'],
            'events.*.verification_reference' => ['nullable', 'string', 'max:32'],
            'events.*.result' => ['required', Rule::in(BoardingPassVerification::STATUSES)],
            'events.*.verification_method' => ['required', Rule::in([VerificationRecord::METHOD_OFFLINE])],
            'events.*.gate' => ['nullable', 'string', 'max:50'],
            'events.*.device_identifier' => ['nullable', 'string', 'max:100'],
            'events.*.verified_at' => ['required', 'date'],
            'events.*.notes' => ['nullable', 'string', 'max:5000'],
            'events.*.approved_at' => ['nullable', 'date'],
            'events.*.event_signature' => ['required', 'string', 'size:64', 'regex:/\A[a-f0-9]{64}\z/'],
        ]);

        $results = [];
        foreach ($validated['events'] as $event) {
            $results[] = $this->synchronizeEvent($event, $terminal, $signature, $auditLogService);
        }

        return response()->json(['results' => $results]);
    }

    public function closeout(Request $request, AuditLogService $auditLogService): JsonResponse
    {
        $terminal = $this->terminal($request);
        $validated = $request->validate([
            'lease_ids' => ['required', 'array', 'max:2000'],
            'lease_ids.*' => ['required', 'uuid'],
        ]);

        $released = DB::transaction(function () use ($terminal, $validated, $auditLogService): int {
            $leases = BoardingPassOfflineLease::query()
                ->whereBelongsTo($terminal, 'gateTerminal')
                ->where('status', BoardingPassOfflineLease::STATUS_ACTIVE)
                ->whereIn('lease_id', $validated['lease_ids'])
                ->lockForUpdate()
                ->get();

            foreach ($leases as $lease) {
                $lease->update([
                    'status' => BoardingPassOfflineLease::STATUS_RELEASED,
                    'released_at' => now(),
                ]);
                $auditLogService->record(
                    AuditLog::EVENT_ADMINISTRATOR_CHANGE,
                    subject: $lease->boardingPass,
                    description: 'Offline gate reservation closed by its gate terminal.',
                    properties: [
                        'action' => 'gate_reservation_released',
                        'terminal_id' => $terminal->terminal_id,
                        'lease_id' => $lease->lease_id,
                    ],
                );
            }

            return $leases->count();
        }, attempts: 3);

        return response()->json(['released' => $released]);
    }

    /**
     * @param  array<string, mixed>  $event
     * @return array<string, mixed>
     */
    private function synchronizeEvent(
        array $event,
        GateTerminal $terminal,
        GateSyncSignature $signature,
        AuditLogService $auditLogService,
    ): array {
        return DB::transaction(function () use ($event, $terminal, $signature, $auditLogService): array {
            $existing = VerificationRecord::query()->where('sync_id', $event['idempotency_key'])->first();
            if ($existing !== null) {
                return [
                    'idempotency_key' => $event['idempotency_key'],
                    'status' => $existing->sync_status,
                    'verification_record_id' => $existing->getKey(),
                ];
            }

            if (! $signature->eventIsValid($event, $event['event_signature'], $terminal->api_token)) {
                return [
                    'idempotency_key' => $event['idempotency_key'],
                    'status' => VerificationRecord::SYNC_CONFLICT,
                    'reason' => 'The event signature is invalid.',
                ];
            }

            $staff = User::query()
                ->whereKey($event['staff_id'])
                ->where('role', 'staff')
                ->where('status', 'active')
                ->first();
            if ($staff === null) {
                return [
                    'idempotency_key' => $event['idempotency_key'],
                    'status' => VerificationRecord::SYNC_CONFLICT,
                    'reason' => 'The synchronized staff identity is inactive or not valid.',
                ];
            }

            $pass = isset($event['boarding_pass_id'])
                ? BoardingPass::query()->whereKey($event['boarding_pass_id'])->lockForUpdate()->first()
                : null;
            $lease = isset($event['lease_id'])
                ? BoardingPassOfflineLease::query()
                    ->where('lease_id', $event['lease_id'])
                    ->whereBelongsTo($terminal, 'gateTerminal')
                    ->whereIn('status', [
                        BoardingPassOfflineLease::STATUS_ACTIVE,
                        BoardingPassOfflineLease::STATUS_CONSUMED,
                    ])
                    ->lockForUpdate()
                    ->first()
                : null;
            $verifiedAt = Carbon::parse($event['verified_at']);
            $isValidPassEvent = $pass !== null
                && $pass->pass_reference === ($event['verification_reference'] ?? null)
                && $lease !== null
                && $lease->boarding_pass_id === $pass->getKey()
                && $verifiedAt->lessThanOrEqualTo($lease->offline_valid_until);
            $syncStatus = VerificationRecord::SYNC_SYNCED;
            $reason = $event['notes'] ?? null;

            if ($event['result'] === BoardingPassVerification::STATUS_VALID && ! $isValidPassEvent) {
                $syncStatus = VerificationRecord::SYNC_CONFLICT;
                $reason = 'The offline verification is not covered by an active terminal reservation.';
            }

            $approvedAt = isset($event['approved_at'])
                ? Carbon::parse($event['approved_at'])
                : null;
            if ($approvedAt !== null) {
                $isApprovable = $syncStatus === VerificationRecord::SYNC_SYNCED
                    && $event['result'] === BoardingPassVerification::STATUS_VALID
                    && $pass !== null
                    && $lease?->status === BoardingPassOfflineLease::STATUS_ACTIVE
                    && $pass->pass_status === BoardingPass::STATUS_ACTIVE
                    && $pass->booking->booking_status === Booking::STATUS_CONFIRMED
                    && ! $pass->flight->trashed()
                    && in_array($pass->flight->status, [Flight::STATUS_SCHEDULED, Flight::STATUS_BOARDING], true)
                    && $verifiedAt->lessThanOrEqualTo($approvedAt)
                    && $approvedAt->lessThanOrEqualTo($lease->offline_valid_until)
                    && now()->lessThanOrEqualTo($lease->offline_valid_until);

                if (! $isApprovable) {
                    $syncStatus = VerificationRecord::SYNC_CONFLICT;
                    $reason = 'The boarding approval conflicts with the central boarding-pass state or reservation.';
                } else {
                    $pass->update([
                        'pass_status' => BoardingPass::STATUS_USED,
                        'used_at' => $approvedAt,
                    ]);
                    $lease->update([
                        'status' => BoardingPassOfflineLease::STATUS_CONSUMED,
                        'consumed_at' => $approvedAt,
                    ]);
                }
            }

            $record = VerificationRecord::create([
                'sync_id' => $event['idempotency_key'],
                'boarding_pass_id' => $pass?->getKey(),
                'passenger_id' => $pass?->passenger_id,
                'flight_id' => $pass?->flight_id,
                'staff_id' => $staff->getKey(),
                'verification_reference' => $event['verification_reference'] ?? null,
                'result' => $event['result'],
                'verification_method' => VerificationRecord::METHOD_OFFLINE,
                'gate' => $event['gate'] ?? null,
                'device_identifier' => $event['device_identifier'] ?? $terminal->terminal_id,
                'verified_at' => $verifiedAt,
                'notes' => $reason,
                'sync_status' => $syncStatus,
                'approved_at' => $syncStatus === VerificationRecord::SYNC_SYNCED ? $approvedAt : null,
            ]);

            $auditLogService->record(
                $event['result'] === BoardingPassVerification::STATUS_VALID
                    ? AuditLog::EVENT_QR_VERIFICATION
                    : AuditLog::EVENT_FAILED_VERIFICATION,
                $staff,
                $pass,
                $reason ?? 'Offline boarding pass verification synchronized.',
                [
                    'verification_record_id' => $record->getKey(),
                    'sync_id' => $event['idempotency_key'],
                    'terminal_id' => $terminal->terminal_id,
                    'result' => $event['result'],
                    'sync_status' => $syncStatus,
                    'verification_method' => VerificationRecord::METHOD_OFFLINE,
                ],
            );

            if ($approvedAt !== null && $syncStatus === VerificationRecord::SYNC_SYNCED) {
                $auditLogService->record(
                    AuditLog::EVENT_BOARDING_APPROVED,
                    $staff,
                    $pass,
                    'Offline boarding approval synchronized.',
                    [
                        'verification_record_id' => $record->getKey(),
                        'sync_id' => $event['idempotency_key'],
                        'terminal_id' => $terminal->terminal_id,
                    ],
                );
            }

            return [
                'idempotency_key' => $event['idempotency_key'],
                'status' => $syncStatus,
                'verification_record_id' => $record->getKey(),
                'reason' => $syncStatus === VerificationRecord::SYNC_CONFLICT ? $reason : null,
            ];
        }, attempts: 3);
    }

    private function terminal(Request $request): GateTerminal
    {
        $terminal = $request->attributes->get('gateTerminal');
        abort_unless($terminal instanceof GateTerminal, 401);

        return $terminal;
    }
}
