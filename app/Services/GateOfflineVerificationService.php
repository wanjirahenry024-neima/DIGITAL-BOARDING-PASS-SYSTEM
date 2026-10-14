<?php

namespace App\Services;

use App\Models\BoardingPass;
use App\Models\BoardingPassVerification;
use App\Models\Booking;
use App\Models\Flight;
use App\Models\GatePassSnapshot;
use App\Models\GateVerificationOutbox;
use App\Models\User;
use App\Models\VerificationRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;

class GateOfflineVerificationService
{
    public function __construct(private GateSyncSignature $signature) {}

    /**
     * @param  array{gate?: string|null, device_identifier?: string|null}  $context
     * @return array<string, mixed>
     */
    public function scan(string $payload, User $staff, array $context = []): array
    {
        $parts = explode('|', $payload);
        $wellFormed = count($parts) === 3
            && $parts[0] === BoardingPass::QR_FORMAT_VERSION
            && preg_match('/\ABP-[0-9A-HJKMNP-TV-Z]{26}\z/', $parts[1]) === 1
            && preg_match('/\A[a-f0-9]{64}\z/', $parts[2]) === 1;
        $passReference = isset($parts[1])
            && preg_match('/\ABP-[0-9A-HJKMNP-TV-Z]{26}\z/', $parts[1]) === 1
                ? $parts[1]
                : null;

        return DB::connection('gate_terminal')->transaction(function () use (
            $parts,
            $wellFormed,
            $passReference,
            $staff,
            $context,
        ): array {
            if (! $wellFormed) {
                return $this->record(null, $staff, null, null, $passReference, BoardingPassVerification::STATUS_INVALID, 'The QR verification payload is invalid.', $context);
            }

            $snapshot = GatePassSnapshot::query()
                ->where('pass_reference', $passReference)
                ->where('is_enabled', true)
                ->lockForUpdate()
                ->first();

            if ($snapshot === null) {
                return $this->record(null, $staff, null, null, $passReference, BoardingPassVerification::STATUS_NOT_FOUND, 'This boarding pass is not cached on this gate terminal.', $context);
            }

            $snapshotData = $this->snapshotData($snapshot);
            $token = config('services.gate_terminal.token');

            if (! is_string($token) || $token === ''
                || ! $this->signature->snapshotIsValid($snapshotData, $snapshot->snapshot_signature, $token)) {
                return $this->record(null, $staff, null, null, $passReference, BoardingPassVerification::STATUS_INVALID, 'The cached verification data failed its integrity check.', $context);
            }

            if (! hash_equals($snapshot->verification_token_hash, hash('sha256', $parts[2]))) {
                return $this->record(
                    $snapshot,
                    $staff,
                    $snapshot->boarding_pass_id,
                    $snapshot->flight_id,
                    $passReference,
                    BoardingPassVerification::STATUS_INVALID,
                    'The verification token is invalid.',
                    $context,
                );
            }

            $status = $this->eligibility($snapshot);
            $reason = $status === BoardingPassVerification::STATUS_VALID ? null : $this->reason($status);

            return $this->record($snapshot, $staff, $snapshot->boarding_pass_id, $snapshot->flight_id, $passReference, $status, $reason, $context);
        }, attempts: 3);
    }

    /**
     * @return array<string, mixed>
     */
    public function approve(string $idempotencyKey, User $staff): array
    {
        return DB::connection('gate_terminal')->transaction(function () use ($idempotencyKey, $staff): array {
            $attempt = GateVerificationOutbox::query()
                ->where('idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->firstOrFail();

            if ($attempt->staff_id !== $staff->getKey()) {
                abort(404);
            }

            if ($attempt->approved_at !== null) {
                return $this->result($attempt);
            }

            if ($attempt->result !== BoardingPassVerification::STATUS_VALID || $attempt->lease_id === null) {
                return $this->result($attempt);
            }

            $snapshot = GatePassSnapshot::query()
                ->where('pass_reference', $attempt->verification_reference)
                ->where('lease_id', $attempt->lease_id)
                ->where('is_enabled', true)
                ->lockForUpdate()
                ->first();

            if (
                $snapshot === null
                || $this->eligibility($snapshot) !== BoardingPassVerification::STATUS_VALID
                || now()->greaterThanOrEqualTo($snapshot->offline_valid_until)
            ) {
                return [
                    ...$this->result($attempt),
                    'status' => BoardingPassVerification::STATUS_EXPIRED,
                    'reason' => 'The offline authorization has expired. Synchronize before boarding.',
                    'can_approve' => false,
                ];
            }

            $attempt->update([
                'approved_at' => now(),
                'sync_status' => GateVerificationOutbox::SYNC_PENDING,
                'next_attempt_at' => null,
            ]);
            $snapshot->update(['locally_used_at' => now()]);

            return $this->result($attempt->refresh());
        }, attempts: 3);
    }

    /**
     * @return array<string, int>
     */
    public function counts(): array
    {
        return GateVerificationOutbox::query()
            ->selectRaw('sync_status, COUNT(*) as count')
            ->groupBy('sync_status')
            ->pluck('count', 'sync_status')
            ->all();
    }

    private function eligibility(GatePassSnapshot $snapshot): string
    {
        if ($snapshot->locally_used_at !== null) {
            return BoardingPassVerification::STATUS_USED;
        }

        if ($snapshot->pass_status === BoardingPass::STATUS_USED) {
            return BoardingPassVerification::STATUS_USED;
        }

        if ($snapshot->pass_status === BoardingPass::STATUS_CANCELLED) {
            return BoardingPassVerification::STATUS_CANCELLED;
        }

        if ($snapshot->pass_status === BoardingPass::STATUS_EXPIRED) {
            return BoardingPassVerification::STATUS_EXPIRED;
        }

        if ($snapshot->pass_status !== BoardingPass::STATUS_ACTIVE) {
            return BoardingPassVerification::STATUS_INVALID;
        }

        if (
            $snapshot->booking_status !== Booking::STATUS_CONFIRMED
            || $snapshot->flight_deleted
            || $snapshot->flight_status === Flight::STATUS_CANCELLED
        ) {
            return BoardingPassVerification::STATUS_CANCELLED;
        }

        if (
            now()->greaterThanOrEqualTo($snapshot->offline_valid_until)
            || now()->greaterThanOrEqualTo($snapshot->expires_at)
            || now()->greaterThanOrEqualTo($snapshot->departure_at)
        ) {
            return BoardingPassVerification::STATUS_EXPIRED;
        }

        if (! in_array($snapshot->flight_status, [
            Flight::STATUS_SCHEDULED,
            Flight::STATUS_BOARDING,
        ], true)) {
            return BoardingPassVerification::STATUS_INVALID;
        }

        return BoardingPassVerification::STATUS_VALID;
    }

    private function reason(string $status): string
    {
        return match ($status) {
            BoardingPassVerification::STATUS_USED => 'This boarding pass has already been used on this gate terminal.',
            BoardingPassVerification::STATUS_EXPIRED => 'This boarding pass or offline authorization has expired.',
            BoardingPassVerification::STATUS_CANCELLED => 'The booking or flight has been cancelled.',
            BoardingPassVerification::STATUS_NOT_FOUND => 'This boarding pass is not cached on this gate terminal.',
            default => 'This boarding pass is invalid.',
        };
    }

    /**
     * @param  array{gate?: string|null, device_identifier?: string|null}  $context
     * @return array<string, mixed>
     */
    private function record(
        ?GatePassSnapshot $snapshot,
        User $staff,
        ?int $boardingPassId,
        ?int $flightId,
        ?string $passReference,
        string $status,
        ?string $reason,
        array $context,
    ): array {
        $staffId = config('services.gate_terminal.enabled')
            ? $staff->central_user_id
            : $staff->getKey();

        if ($staffId === null) {
            throw new LogicException('This local staff account is missing its central user ID mapping.');
        }

        $attempt = GateVerificationOutbox::create([
            'idempotency_key' => (string) Str::uuid(),
            'boarding_pass_id' => $boardingPassId,
            'flight_id' => $flightId,
            'passenger_id' => null,
            'staff_id' => $staffId,
            'lease_id' => $snapshot?->lease_id,
            'verification_reference' => $passReference,
            'result' => $status,
            'verification_method' => VerificationRecord::METHOD_OFFLINE,
            'gate' => $context['gate'] ?? null,
            'device_identifier' => $context['device_identifier'] ?? config('services.gate_terminal.id'),
            'verified_at' => now(),
            'notes' => $reason,
            'sync_status' => GateVerificationOutbox::SYNC_PENDING,
            'next_attempt_at' => now(),
        ]);

        return $this->result($attempt, $snapshot);
    }

    /**
     * @return array<string, mixed>
     */
    private function result(GateVerificationOutbox $attempt, ?GatePassSnapshot $snapshot = null): array
    {
        $snapshot ??= GatePassSnapshot::query()
            ->where('pass_reference', $attempt->verification_reference)
            ->first();

        return [
            'verification_id' => $attempt->idempotency_key,
            'verification_record_id' => null,
            'status' => $attempt->result,
            'reason' => $attempt->notes,
            'verified_at' => $attempt->verified_at->toIso8601String(),
            'can_approve' => $attempt->result === BoardingPassVerification::STATUS_VALID && $attempt->approved_at === null,
            'approved' => $attempt->approved_at !== null,
            'passenger' => $snapshot?->passenger_name,
            'flight' => $snapshot?->flight_number,
            'destination' => $snapshot?->destination,
            'seat' => $snapshot?->seat_number,
            'boarding_pass_id' => $attempt->verification_reference,
            'verification_method' => VerificationRecord::METHOD_OFFLINE,
        ];
    }

    /**
     * @return array<string, int|string|bool>
     */
    private function snapshotData(GatePassSnapshot $snapshot): array
    {
        return [
            'boarding_pass_id' => $snapshot->boarding_pass_id,
            'pass_reference' => $snapshot->pass_reference,
            'passenger_name' => $snapshot->passenger_name,
            'verification_token_hash' => $snapshot->verification_token_hash,
            'pass_status' => $snapshot->pass_status,
            'booking_status' => $snapshot->booking_status,
            'flight_id' => $snapshot->flight_id,
            'flight_status' => $snapshot->flight_status,
            'flight_deleted' => $snapshot->flight_deleted,
            'flight_number' => $snapshot->flight_number,
            'destination' => $snapshot->destination,
            'seat_number' => $snapshot->seat_number,
            'departure_at' => $snapshot->departure_at->format('Y-m-d H:i:s'),
            'expires_at' => $snapshot->expires_at->format('Y-m-d H:i:s'),
            'lease_id' => $snapshot->lease_id,
            'offline_valid_until' => $snapshot->offline_valid_until->format('Y-m-d H:i:s'),
        ];
    }
}
