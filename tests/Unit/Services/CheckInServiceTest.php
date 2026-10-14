<?php

namespace Tests\Unit\Services;

use App\Models\BoardingPass;
use App\Models\Booking;
use App\Models\CheckIn;
use App\Models\Flight;
use App\Models\User;
use App\Services\CheckInService;
use App\Services\QrCodeGenerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CheckInServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_check_in_creation_assigns_a_seat_and_issues_one_active_pass(): void
    {
        Notification::fake();
        $passenger = User::factory()->create(['role' => 'passenger']);
        $flight = Flight::factory()->create(['total_seats' => 2]);
        $booking = $this->createBooking($passenger, $flight);

        $checkIn = app(CheckInService::class)->create(
            $booking,
            $passenger,
            app(QrCodeGenerationService::class),
        );

        $boardingPass = $checkIn->boardingPass;
        $this->assertSame(CheckIn::STATUS_CHECKED_IN, $checkIn->status);
        $this->assertSame('001', $checkIn->seat_number);
        $this->assertSame($booking->getKey(), $boardingPass->booking_id);
        $this->assertSame(BoardingPass::STATUS_ACTIVE, $boardingPass->pass_status);
        $this->assertSame($checkIn->seat_number, $boardingPass->seat_number);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $boardingPass->verification_token);
        $this->assertDatabaseCount('check_ins', 1);
        $this->assertDatabaseCount('boarding_passes', 1);
    }

    public function test_check_in_creation_rejects_a_booking_that_already_has_a_check_in(): void
    {
        Notification::fake();
        $passenger = User::factory()->create(['role' => 'passenger']);
        $flight = Flight::factory()->create();
        $booking = $this->createBooking($passenger, $flight);
        $service = app(CheckInService::class);

        $service->create($booking, $passenger, app(QrCodeGenerationService::class));

        try {
            $service->create($booking, $passenger, app(QrCodeGenerationService::class));
            $this->fail('Expected duplicate check-in to be rejected.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                'This booking has already been checked in.',
                $exception->errors()['booking'][0],
            );
        }

        $this->assertDatabaseCount('check_ins', 1);
        $this->assertDatabaseCount('boarding_passes', 1);
    }

    private function createBooking(User $passenger, Flight $flight): Booking
    {
        return $passenger->bookings()->create([
            'booking_reference' => 'DBP-'.strtoupper((string) Str::ulid()),
            'flight_id' => $flight->getKey(),
            'booking_status' => Booking::STATUS_CONFIRMED,
            'booking_date' => now(),
            'number_of_passengers' => 1,
        ]);
    }
}
