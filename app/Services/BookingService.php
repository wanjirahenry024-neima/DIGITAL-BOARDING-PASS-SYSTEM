<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\CheckIn;
use App\Models\Flight;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BookingService
{
    public function __construct(
        private AuditLogService $auditLogService,
        private PassengerNotificationService $passengerNotificationService,
    ) {}

    public function create(User $passenger, int $flightId, int $numberOfPassengers): Booking
    {
        return DB::transaction(function () use ($passenger, $flightId, $numberOfPassengers): Booking {
            $flight = Flight::query()
                ->whereKey($flightId)
                ->lockForUpdate()
                ->firstOrFail();

            if (
                ! in_array($flight->status, [Flight::STATUS_SCHEDULED, Flight::STATUS_BOARDING], true)
                || $flight->departure_date->isBefore(today())
            ) {
                throw ValidationException::withMessages([
                    'flight_id' => 'This flight is not accepting bookings.',
                ]);
            }

            if ($flight->available_seats < $numberOfPassengers) {
                throw ValidationException::withMessages([
                    'number_of_passengers' => 'There are not enough seats available for this flight.',
                ]);
            }

            $flight->decrement('available_seats', $numberOfPassengers);

            $booking = $passenger->bookings()->create([
                'booking_reference' => 'DBP-'.strtoupper((string) Str::ulid()),
                'flight_id' => $flight->getKey(),
                'booking_status' => Booking::STATUS_CONFIRMED,
                'booking_date' => now(),
                'number_of_passengers' => $numberOfPassengers,
            ]);

            $this->auditLogService->record(
                AuditLog::EVENT_BOOKING_CREATED,
                $passenger,
                $booking,
                'Booking created.',
                [
                    'booking_reference' => $booking->booking_reference,
                    'flight_id' => $flight->getKey(),
                    'number_of_passengers' => $numberOfPassengers,
                ],
            );
            $this->passengerNotificationService->bookingConfirmed($booking);

            return $booking;
        }, attempts: 3);
    }

    public function cancel(Booking $booking, User $actor): Booking
    {
        return DB::transaction(function () use ($booking, $actor): Booking {
            $lockedBooking = Booking::query()
                ->whereKey($booking->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $flight = Flight::withTrashed()
                ->whereKey($lockedBooking->flight_id)
                ->lockForUpdate()
                ->firstOrFail();

            if (
                $lockedBooking->booking_status !== Booking::STATUS_CONFIRMED
                || CheckIn::query()->where('booking_id', $lockedBooking->getKey())->exists()
                || $flight->departure_date->isBefore(today())
                || in_array($flight->status, [Flight::STATUS_DEPARTED, Flight::STATUS_COMPLETED], true)
            ) {
                throw ValidationException::withMessages([
                    'booking' => 'This booking can no longer be cancelled.',
                ]);
            }

            if (in_array($flight->status, [Flight::STATUS_SCHEDULED, Flight::STATUS_BOARDING], true)) {
                $flight->available_seats = min(
                    $flight->total_seats,
                    $flight->available_seats + $lockedBooking->number_of_passengers,
                );
                $flight->save();
            }

            $lockedBooking->update(['booking_status' => Booking::STATUS_CANCELLED]);
            $this->auditLogService->record(
                AuditLog::EVENT_BOOKING_CANCELLED,
                $actor,
                $lockedBooking,
                'Booking cancelled.',
                [
                    'booking_reference' => $lockedBooking->booking_reference,
                    'flight_id' => $flight->getKey(),
                    'status' => [
                        'from' => Booking::STATUS_CONFIRMED,
                        'to' => Booking::STATUS_CANCELLED,
                    ],
                ],
            );

            if ($actor->isAdmin()) {
                $this->auditLogService->record(
                    AuditLog::EVENT_ADMINISTRATOR_CHANGE,
                    $actor,
                    $lockedBooking,
                    'Administrator cancelled a booking.',
                    ['action' => AuditLog::EVENT_BOOKING_CANCELLED],
                );
            }

            $this->passengerNotificationService->bookingCancelled($lockedBooking);

            return $lockedBooking->refresh();
        }, attempts: 3);
    }
}
