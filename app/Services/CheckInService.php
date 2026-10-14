<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\BoardingPass;
use App\Models\Booking;
use App\Models\CheckIn;
use App\Models\Flight;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CheckInService
{
    public function __construct(
        private AuditLogService $auditLogService,
        private PassengerNotificationService $passengerNotificationService,
    ) {}

    public function unavailableReason(Booking $booking): ?string
    {
        if ($booking->booking_status !== Booking::STATUS_CONFIRMED) {
            return 'Only confirmed bookings can check in.';
        }

        $flight = $booking->flight;

        if ($flight->trashed() || $flight->status === Flight::STATUS_CANCELLED) {
            return 'This flight has been cancelled.';
        }

        if (! in_array($flight->status, [Flight::STATUS_SCHEDULED, Flight::STATUS_BOARDING], true)) {
            return 'This flight is no longer accepting check-in.';
        }

        if (now()->greaterThan($this->checkInDeadline($flight))) {
            return 'Check-in closes one hour before departure.';
        }

        if ($booking->checkIn()->exists()) {
            return 'This booking has already been checked in.';
        }

        return null;
    }

    public function create(
        Booking $booking,
        User $passenger,
        QrCodeGenerationService $qrCodeGenerationService,
    ): CheckIn {
        return DB::transaction(function () use ($booking, $passenger, $qrCodeGenerationService): CheckIn {
            $lockedBooking = Booking::query()
                ->whereKey($booking->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $flight = Flight::withTrashed()
                ->whereKey($lockedBooking->flight_id)
                ->lockForUpdate()
                ->firstOrFail();
            $lockedBooking->setRelation('flight', $flight);

            $unavailableReason = $this->unavailableReason($lockedBooking);

            if ($unavailableReason !== null) {
                throw ValidationException::withMessages([
                    'booking' => $unavailableReason,
                ]);
            }

            $seatNumber = $this->nextSeatNumber($flight);
            $verificationToken = bin2hex(random_bytes(32));

            $checkIn = CheckIn::create([
                'booking_id' => $lockedBooking->getKey(),
                'passenger_id' => $lockedBooking->passenger_id,
                'flight_id' => $flight->getKey(),
                'check_in_reference' => 'CI-'.strtoupper((string) Str::ulid()),
                'checked_in_at' => now(),
                'seat_number' => $seatNumber,
                'status' => CheckIn::STATUS_CHECKED_IN,
            ]);

            $boardingPass = $checkIn->boardingPass()->create([
                'pass_reference' => 'BP-'.strtoupper((string) Str::ulid()),
                'booking_id' => $lockedBooking->getKey(),
                'passenger_id' => $lockedBooking->passenger_id,
                'flight_id' => $flight->getKey(),
                'seat_number' => $seatNumber,
                'pass_status' => BoardingPass::STATUS_ACTIVE,
                'issued_at' => now(),
                'expires_at' => $this->checkInDeadline($flight)->addHour(),
                'verification_token' => $verificationToken,
                'verification_token_hash' => hash('sha256', $verificationToken),
            ]);

            $qrCodeGenerationService->generate($boardingPass);

            $this->auditLogService->record(
                AuditLog::EVENT_CHECK_IN,
                $passenger,
                $checkIn,
                'Passenger checked in.',
                [
                    'check_in_reference' => $checkIn->check_in_reference,
                    'booking_id' => $lockedBooking->getKey(),
                    'seat_number' => $seatNumber,
                ],
            );
            $this->auditLogService->record(
                AuditLog::EVENT_BOARDING_PASS_CREATED,
                $passenger,
                $boardingPass,
                'Boarding pass issued.',
                ['pass_reference' => $boardingPass->pass_reference],
            );
            $this->passengerNotificationService->checkInCompleted($checkIn);

            return $checkIn->load('boardingPass');
        }, attempts: 3);
    }

    private function checkInDeadline(Flight $flight): Carbon
    {
        return $flight->departure_date
            ->copy()
            ->setTimeFromTimeString($flight->departure_time)
            ->subHour();
    }

    private function nextSeatNumber(Flight $flight): string
    {
        $assignedSeats = $flight->checkIns()->pluck('seat_number')->all();

        for ($seat = 1; $seat <= $flight->total_seats; $seat++) {
            $seatNumber = str_pad((string) $seat, 3, '0', STR_PAD_LEFT);

            if (! in_array($seatNumber, $assignedSeats, true)) {
                return $seatNumber;
            }
        }

        throw ValidationException::withMessages([
            'flight' => 'There are no seats available for check-in on this flight.',
        ]);
    }
}
