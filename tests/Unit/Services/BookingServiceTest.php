<?php

namespace Tests\Unit\Services;

use App\Models\Booking;
use App\Models\Flight;
use App\Models\User;
use App\Services\BookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class BookingServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_booking_creation_confirms_booking_and_decrements_available_seats(): void
    {
        Notification::fake();
        $passenger = User::factory()->create(['role' => 'passenger']);
        $flight = Flight::factory()->create(['available_seats' => 5]);

        $booking = app(BookingService::class)->create($passenger, $flight->getKey(), 2);

        $this->assertSame(Booking::STATUS_CONFIRMED, $booking->booking_status);
        $this->assertSame(2, $booking->number_of_passengers);
        $this->assertSame(3, $flight->fresh()->available_seats);
        $this->assertDatabaseHas('bookings', ['id' => $booking->getKey()]);
    }

    public function test_booking_creation_does_not_decrement_seats_when_capacity_is_insufficient(): void
    {
        Notification::fake();
        $passenger = User::factory()->create(['role' => 'passenger']);
        $flight = Flight::factory()->create(['available_seats' => 1]);

        try {
            app(BookingService::class)->create($passenger, $flight->getKey(), 2);
            $this->fail('Expected insufficient flight capacity to reject booking.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                'There are not enough seats available for this flight.',
                $exception->errors()['number_of_passengers'][0],
            );
        }

        $this->assertSame(1, $flight->fresh()->available_seats);
        $this->assertDatabaseCount('bookings', 0);
    }
}
