<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\BoardingPass;
use App\Models\BoardingPassOfflineLease;
use App\Models\BoardingPassVerification;
use App\Models\Booking;
use App\Models\CheckIn;
use App\Models\Flight;
use App\Models\User;
use App\Models\VerificationRecord;
use Illuminate\Support\Facades\DB;

class VerifyBoardingPassService
{
    public function __construct(private AuditLogService $auditLogService) {}

    /**
     * @param  array{gate?: string|null, device_identifier?: string|null}  $context
     * @return array<string, mixed>
     */
    public function scan(string $payload, User $staff, array $context = []): array
    {
        $parts = explode('|', $payload);
        $isWellFormed = count($parts) === 3
            && $parts[0] === BoardingPass::QR_FORMAT_VERSION
            && preg_match('/\ABP-[0-9A-HJKMNP-TV-Z]{26}\z/', $parts[1]) === 1
            && preg_match('/\A[a-f0-9]{64}\z/', $parts[2]) === 1;
        $passReference = isset($parts[1])
            && preg_match('/\ABP-[0-9A-HJKMNP-TV-Z]{26}\z/', $parts[1]) === 1
                ? $parts[1]
                : null;

        return DB::transaction(function () use ($isWellFormed, $passReference, $parts, $staff, $context): array {
            if (! $isWellFormed) {
                $verification = $this->record(
                    null,
                    $staff,
                    $passReference,
                    BoardingPassVerification::STATUS_INVALID,
                    'The QR verification payload is invalid.',
                    $context,
                );

                return $this->result($verification);
            }

            $token = $parts[2];
            $boardingPass = BoardingPass::query()
                ->where('pass_reference', $passReference)
                ->lockForUpdate()
                ->first();

            if ($boardingPass === null) {
                $verification = $this->record(
                    null,
                    $staff,
                    $passReference,
                    BoardingPassVerification::STATUS_NOT_FOUND,
                    'No boarding pass matches this reference.',
                    $context,
                );

                return $this->result($verification);
            }

            if (! hash_equals((string) $boardingPass->verification_token_hash, hash('sha256', $token))) {
                $verification = $this->record(
                    $boardingPass,
                    $staff,
                    $passReference,
                    BoardingPassVerification::STATUS_INVALID,
                    'The verification token is invalid.',
                    $context,
                );

                return $this->result($verification);
            }

            $boardingPass->load(['booking', 'checkIn', 'flight', 'passenger']);
            [$status, $reason] = $this->eligibility($boardingPass);

            if ($status !== BoardingPassVerification::STATUS_VALID) {
                if (in_array($status, [
                    BoardingPassVerification::STATUS_CANCELLED,
                    BoardingPassVerification::STATUS_EXPIRED,
                ], true)) {
                    $boardingPass->update(['pass_status' => $status]);
                }
            }

            $verification = $this->record(
                $boardingPass,
                $staff,
                $passReference,
                $status,
                $reason,
                $context,
            );

            return $this->result($verification, $boardingPass);
        }, attempts: 3);
    }

    /**
     * @return array<string, mixed>
     */
    public function approve(BoardingPassVerification $verification, User $staff): array
    {
        return DB::transaction(function () use ($verification, $staff): array {
            $lockedVerification = BoardingPassVerification::query()
                ->whereKey($verification->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedVerification->status !== BoardingPassVerification::STATUS_VALID) {
                return $this->result(
                    $lockedVerification->load(['boardingPass.passenger', 'boardingPass.flight']),
                    $lockedVerification->boardingPass,
                );
            }

            $boardingPass = BoardingPass::query()
                ->whereKey($lockedVerification->boarding_pass_id)
                ->lockForUpdate()
                ->first();

            if ($boardingPass === null) {
                $lockedVerification->update([
                    'status' => BoardingPassVerification::STATUS_INVALID,
                    'reason' => 'The boarding pass no longer exists.',
                ]);

                return $this->result($lockedVerification);
            }

            $boardingPass->load(['booking', 'checkIn', 'flight', 'passenger']);
            [$status, $reason] = $this->eligibility($boardingPass);

            if ($status !== BoardingPassVerification::STATUS_VALID) {
                $lockedVerification->update([
                    'status' => $status,
                    'reason' => $reason,
                ]);

                if (in_array($status, [
                    BoardingPassVerification::STATUS_CANCELLED,
                    BoardingPassVerification::STATUS_EXPIRED,
                ], true)) {
                    $boardingPass->update(['pass_status' => $status]);
                }

                return $this->result($lockedVerification, $boardingPass);
            }

            $boardingPass->update([
                'pass_status' => BoardingPass::STATUS_USED,
                'used_at' => now(),
            ]);
            $lockedVerification->update([
                'status' => BoardingPassVerification::STATUS_USED,
                'reason' => 'Boarding approved.',
                'approved_by' => $staff->getKey(),
                'approved_at' => now(),
            ]);

            $this->auditLogService->record(
                AuditLog::EVENT_BOARDING_APPROVED,
                $staff,
                $boardingPass,
                'Boarding approved.',
                [
                    'verification_id' => $lockedVerification->getKey(),
                    'verification_record_id' => $lockedVerification->verification_record_id,
                    'pass_reference' => $boardingPass->pass_reference,
                ],
            );

            return $this->result($lockedVerification, $boardingPass);
        }, attempts: 3);
    }

    /**
     * @return array{0: string, 1: ?string}
     */
    private function eligibility(BoardingPass $boardingPass): array
    {
        if ($boardingPass->pass_status === BoardingPass::STATUS_USED) {
            return [BoardingPassVerification::STATUS_USED, 'This boarding pass has already been used.'];
        }

        if ($boardingPass->pass_status === BoardingPass::STATUS_CANCELLED) {
            return [BoardingPassVerification::STATUS_CANCELLED, 'This boarding pass has been cancelled.'];
        }

        if ($boardingPass->pass_status === BoardingPass::STATUS_EXPIRED) {
            return [BoardingPassVerification::STATUS_EXPIRED, 'This boarding pass has expired.'];
        }

        if ($boardingPass->pass_status !== BoardingPass::STATUS_ACTIVE) {
            return [BoardingPassVerification::STATUS_INVALID, 'This boarding pass is invalid.'];
        }

        if (
            BoardingPassOfflineLease::query()
                ->where('boarding_pass_id', $boardingPass->getKey())
                ->where('status', BoardingPassOfflineLease::STATUS_ACTIVE)
                ->exists()
        ) {
            return [
                BoardingPassVerification::STATUS_CONFLICT,
                'This boarding pass is exclusively reserved to an offline gate terminal.',
            ];
        }

        if (
            $boardingPass->booking->booking_status === Booking::STATUS_CANCELLED
            || $boardingPass->flight->trashed()
            || $boardingPass->flight->status === Flight::STATUS_CANCELLED
        ) {
            return [BoardingPassVerification::STATUS_CANCELLED, 'The booking or flight has been cancelled.'];
        }

        if (
            $boardingPass->flight->departure_date->isBefore(today())
            || now()->greaterThanOrEqualTo($boardingPass->expires_at)
        ) {
            return [BoardingPassVerification::STATUS_EXPIRED, 'This boarding pass has expired.'];
        }

        if (
            $boardingPass->booking->booking_status !== Booking::STATUS_CONFIRMED
            || (string) $boardingPass->booking_id !== (string) $boardingPass->checkIn->booking_id
            || (string) $boardingPass->passenger_id !== (string) $boardingPass->checkIn->passenger_id
            || (string) $boardingPass->flight_id !== (string) $boardingPass->checkIn->flight_id
            || $boardingPass->seat_number !== $boardingPass->checkIn->seat_number
            || $boardingPass->checkIn->status !== CheckIn::STATUS_CHECKED_IN
        ) {
            return [BoardingPassVerification::STATUS_INVALID, 'The booking or check-in details are invalid.'];
        }

        if (! in_array($boardingPass->flight->status, [Flight::STATUS_SCHEDULED, Flight::STATUS_BOARDING], true)) {
            return [BoardingPassVerification::STATUS_INVALID, 'This flight is not accepting passengers for boarding.'];
        }

        return [BoardingPassVerification::STATUS_VALID, null];
    }

    private function record(
        ?BoardingPass $boardingPass,
        User $staff,
        ?string $passReference,
        string $status,
        ?string $reason,
        array $context = [],
    ): BoardingPassVerification {
        $record = VerificationRecord::create([
            'boarding_pass_id' => $boardingPass?->getKey(),
            'passenger_id' => $boardingPass?->passenger_id,
            'flight_id' => $boardingPass?->flight_id,
            'staff_id' => $staff->getKey(),
            'verification_reference' => $passReference,
            'result' => $status,
            'verification_method' => VerificationRecord::METHOD_ONLINE,
            'gate' => $context['gate'] ?? null,
            'device_identifier' => $context['device_identifier'] ?? null,
            'verified_at' => now(),
            'notes' => $reason,
        ]);

        $verification = BoardingPassVerification::create([
            'boarding_pass_id' => $boardingPass?->getKey(),
            'verification_record_id' => $record->getKey(),
            'staff_id' => $staff->getKey(),
            'pass_reference' => $passReference,
            'status' => $status,
            'reason' => $reason,
            'verified_at' => now(),
        ]);

        $this->auditLogService->record(
            $status === BoardingPassVerification::STATUS_VALID
                ? AuditLog::EVENT_QR_VERIFICATION
                : AuditLog::EVENT_FAILED_VERIFICATION,
            $staff,
            $boardingPass,
            $reason ?? 'Boarding pass QR code verified.',
            [
                'verification_id' => $verification->getKey(),
                'verification_record_id' => $record->getKey(),
                'pass_reference' => $passReference,
                'result' => $status,
            ],
        );

        return $verification;
    }

    /**
     * @return array<string, mixed>
     */
    private function result(
        BoardingPassVerification $verification,
        ?BoardingPass $boardingPass = null,
    ): array {
        $verification->loadMissing(['boardingPass.passenger', 'boardingPass.flight']);

        return [
            'verification_id' => $verification->getKey(),
            'verification_record_id' => $verification->verification_record_id,
            'status' => $verification->status,
            'reason' => $verification->reason,
            'verified_at' => $verification->verified_at->toIso8601String(),
            'can_approve' => $verification->status === BoardingPassVerification::STATUS_VALID,
            'approved' => $verification->approved_at !== null,
            'passenger' => $boardingPass?->passenger->full_name,
            'flight' => $boardingPass?->flight->flight_number,
            'destination' => $boardingPass?->flight->arrival_airport,
            'seat' => $boardingPass?->seat_number,
            'boarding_pass_id' => $boardingPass?->pass_reference,
        ];
    }
}
