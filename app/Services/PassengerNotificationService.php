<?php

namespace App\Services;

use App\Models\BoardingPass;
use App\Models\Booking;
use App\Models\CheckIn;
use App\Models\Flight;
use App\Models\User;
use App\Notifications\PassengerUpdateNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class PassengerNotificationService
{
    public function registered(User $passenger): void
    {
        $this->send(
            $passenger,
            'registration',
            'Your account is ready',
            'Your Digital Boarding Pass account has been created.',
            route('dashboard'),
        );
    }

    public function bookingConfirmed(Booking $booking): void
    {
        $booking->loadMissing('flight');

        $this->send(
            $booking->passenger,
            'booking_confirmed',
            'Booking confirmed',
            sprintf(
                'Booking %s is confirmed for flight %s.',
                $booking->booking_reference,
                $booking->flight->flight_number,
            ),
            route('bookings.show', $booking),
        );
    }

    public function bookingCancelled(Booking $booking): void
    {
        $booking->loadMissing('flight');

        $this->send(
            $booking->passenger,
            'booking_cancelled',
            'Booking cancelled',
            sprintf(
                'Booking %s for flight %s has been cancelled.',
                $booking->booking_reference,
                $booking->flight->flight_number,
            ),
            route('bookings.show', $booking),
        );
    }

    public function checkInCompleted(CheckIn $checkIn): void
    {
        $checkIn->loadMissing('flight', 'passenger', 'boardingPass');

        $this->send(
            $checkIn->passenger,
            'check_in_completed',
            'Check-in complete',
            sprintf('You are checked in for flight %s.', $checkIn->flight->flight_number),
            route('check-ins.confirmation', $checkIn),
        );

        $boardingPass = $checkIn->boardingPass;
        $this->boardingPassIssued($boardingPass);
    }

    public function boardingPassIssued(BoardingPass $boardingPass): void
    {
        $boardingPass->loadMissing('flight');

        $this->send(
            $boardingPass->passenger,
            'boarding_pass_issued',
            'Boarding pass issued',
            sprintf('Your boarding pass for flight %s is ready.', $boardingPass->flight->flight_number),
            route('boarding-passes.show', $boardingPass),
        );
    }

    public function flightStatusChanged(Flight $flight, string $status): void
    {
        $passengers = User::query()
            ->where('role', 'passenger')
            ->whereHas('bookings', function (Builder $query) use ($flight): void {
                $query->where('flight_id', $flight->getKey())
                    ->where('booking_status', Booking::STATUS_CONFIRMED);
            });

        $passengers->chunkById(100, function (Collection $passengerChunk) use ($flight, $status): void {
            foreach ($passengerChunk as $passenger) {
                $this->send(
                    $passenger,
                    'flight_status_changed',
                    'Flight status updated',
                    sprintf('Flight %s status is now %s.', $flight->flight_number, $status),
                    route('bookings.index'),
                );
            }
        });
    }

    private function send(
        User $passenger,
        string $event,
        string $title,
        string $message,
        string $url,
    ): void {
        if ($passenger->role !== 'passenger') {
            return;
        }

        $passenger->notify(new PassengerUpdateNotification($event, $title, $message, $url));
    }
}
