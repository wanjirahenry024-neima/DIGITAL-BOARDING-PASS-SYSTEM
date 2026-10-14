<?php

namespace Tests\Unit\Services;

use App\Models\BoardingPass;
use App\Models\BoardingPassVerification;
use App\Models\Booking;
use App\Models\CheckIn;
use App\Models\Flight;
use App\Models\User;
use App\Services\VerifyBoardingPassService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class VerifyBoardingPassServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_qr_scan_can_be_approved_once_and_replay_is_rejected(): void
    {
        Notification::fake();
        $passenger = User::factory()->create(['role' => 'passenger']);
        $staff = User::factory()->create(['role' => 'staff']);
        $flight = Flight::factory()->create();
        $booking = $passenger->bookings()->create([
            'booking_reference' => 'DBP-'.strtoupper((string) Str::ulid()),
            'flight_id' => $flight->getKey(),
            'booking_status' => Booking::STATUS_CONFIRMED,
            'booking_date' => now(),
            'number_of_passengers' => 1,
        ]);
        $checkIn = CheckIn::create([
            'booking_id' => $booking->getKey(),
            'passenger_id' => $passenger->getKey(),
            'flight_id' => $flight->getKey(),
            'check_in_reference' => 'CI-'.strtoupper((string) Str::ulid()),
            'checked_in_at' => now(),
            'seat_number' => '001',
            'status' => CheckIn::STATUS_CHECKED_IN,
        ]);
        $token = bin2hex(random_bytes(32));
        $boardingPass = $checkIn->boardingPass()->create([
            'pass_reference' => 'BP-'.strtoupper((string) Str::ulid()),
            'booking_id' => $booking->getKey(),
            'passenger_id' => $passenger->getKey(),
            'flight_id' => $flight->getKey(),
            'seat_number' => $checkIn->seat_number,
            'pass_status' => BoardingPass::STATUS_ACTIVE,
            'issued_at' => now(),
            'expires_at' => now()->addDay(),
            'verification_token' => $token,
            'verification_token_hash' => hash('sha256', $token),
        ]);
        $service = app(VerifyBoardingPassService::class);
        $payload = implode('|', [BoardingPass::QR_FORMAT_VERSION, $boardingPass->pass_reference, $token]);

        $scan = $service->scan($payload, $staff);
        $approval = $service->approve(
            BoardingPassVerification::query()->findOrFail($scan['verification_id']),
            $staff,
        );
        $replay = $service->scan($payload, $staff);

        $this->assertSame(BoardingPassVerification::STATUS_VALID, $scan['status']);
        $this->assertSame(BoardingPassVerification::STATUS_USED, $approval['status']);
        $this->assertSame(BoardingPassVerification::STATUS_USED, $replay['status']);
        $this->assertSame(BoardingPass::STATUS_USED, $boardingPass->fresh()->pass_status);
        $this->assertNotNull($boardingPass->fresh()->used_at);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'boarding_approved',
            'actor_id' => $staff->getKey(),
            'subject_id' => $boardingPass->getKey(),
        ]);
    }
}
