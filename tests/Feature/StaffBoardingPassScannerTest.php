<?php

namespace Tests\Feature;

use App\Models\BoardingPass;
use App\Models\BoardingPassVerification;
use App\Models\Booking;
use App\Models\Flight;
use App\Models\User;
use App\Models\VerificationRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('security')]
class StaffBoardingPassScannerTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_dashboard_displays_camera_scanner_stats_recent_attempts_and_offline_status(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);

        $this->actingAs($staff)
            ->get(route('staff.dashboard'))
            ->assertOk()
            ->assertSee('Scan boarding pass')
            ->assertSee('Recent verifications')
            ->assertSee('Verifications today')
            ->assertSee('Offline mode: Not available')
            ->assertSee('Start camera')
            ->assertSee('BarcodeDetector')
            ->assertSee('Approve boarding');
    }

    public function test_only_authenticated_staff_can_access_dashboard_and_scanner_endpoints(): void
    {
        $passenger = User::factory()->create(['role' => 'passenger']);
        $admin = User::factory()->create(['role' => 'admin']);
        $staff = User::factory()->create(['role' => 'staff']);
        $payload = 'DBP1|BP-'.str_repeat('A', 26).'|'.str_repeat('a', 64);

        $this->get(route('staff.dashboard'))->assertRedirect(route('login'));
        $this->postJson(route('staff.boarding-passes.verify'), ['payload' => $payload])
            ->assertUnauthorized();

        foreach ([$passenger, $admin] as $user) {
            $this->actingAs($user)
                ->get(route('staff.dashboard'))
                ->assertForbidden();

            $this->postJson(route('staff.boarding-passes.verify'), ['payload' => $payload])
                ->assertForbidden();
        }

        $this->actingAs($staff)
            ->postJson(route('staff.boarding-passes.verify'), ['payload' => $payload])
            ->assertOk()
            ->assertJsonPath('status', BoardingPassVerification::STATUS_NOT_FOUND);

        $this->assertDatabaseCount('boarding_pass_verifications', 1);
    }

    public function test_not_found_scan_is_recorded_with_clear_result(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $payload = 'DBP1|BP-'.str_repeat('B', 26).'|'.str_repeat('b', 64);

        $this->actingAs($staff)
            ->postJson(route('staff.boarding-passes.verify'), ['payload' => $payload])
            ->assertOk()
            ->assertJson([
                'status' => BoardingPassVerification::STATUS_NOT_FOUND,
                'reason' => 'No boarding pass matches this reference.',
                'passenger' => null,
                'flight' => null,
                'destination' => null,
                'seat' => null,
                'boarding_pass_id' => null,
                'can_approve' => false,
            ]);

        $this->assertDatabaseHas('boarding_pass_verifications', [
            'staff_id' => $staff->getKey(),
            'pass_reference' => 'BP-'.str_repeat('B', 26),
            'status' => BoardingPassVerification::STATUS_NOT_FOUND,
        ]);
        $this->assertDatabaseHas('verification_records', [
            'staff_id' => $staff->getKey(),
            'verification_reference' => 'BP-'.str_repeat('B', 26),
            'result' => VerificationRecord::RESULT_NOT_FOUND,
            'verification_method' => VerificationRecord::METHOD_ONLINE,
        ]);
    }

    public function test_only_one_staff_approval_can_consume_a_pass_after_multiple_valid_scans(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $passenger = User::factory()->create(['role' => 'passenger']);
        $boardingPass = $this->issuePass($passenger);
        $payload = $this->payloadFor($boardingPass);

        $firstScan = $this->actingAs($staff)
            ->postJson(route('staff.boarding-passes.verify'), ['payload' => $payload])
            ->assertOk()
            ->assertJsonPath('status', BoardingPassVerification::STATUS_VALID);
        $secondScan = $this->postJson(route('staff.boarding-passes.verify'), ['payload' => $payload])
            ->assertOk()
            ->assertJsonPath('status', BoardingPassVerification::STATUS_VALID);

        $this->postJson(route('staff.boarding-pass-verifications.approve', $firstScan->json('verification_id')))
            ->assertOk()
            ->assertJsonPath('status', BoardingPassVerification::STATUS_USED);

        $this->postJson(route('staff.boarding-pass-verifications.approve', $secondScan->json('verification_id')))
            ->assertConflict()
            ->assertJsonPath('status', BoardingPassVerification::STATUS_USED)
            ->assertJsonPath('approved', false)
            ->assertJsonPath('reason', 'This boarding pass has already been used.');

        $this->assertDatabaseHas('boarding_passes', [
            'id' => $boardingPass->getKey(),
            'pass_status' => BoardingPass::STATUS_USED,
        ]);
        $this->assertNotNull($boardingPass->fresh()->used_at);
        $this->assertDatabaseCount('boarding_pass_verifications', 2);
    }

    public function test_invalid_attempt_cannot_be_approved_and_is_preserved(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $passenger = User::factory()->create(['role' => 'passenger']);
        $boardingPass = $this->issuePass($passenger);
        $invalidPayload = 'DBP1|'.$boardingPass->pass_reference.'|'.str_repeat('0', 64);

        $scanResponse = $this->actingAs($staff)
            ->postJson(route('staff.boarding-passes.verify'), ['payload' => $invalidPayload])
            ->assertOk()
            ->assertJsonPath('status', BoardingPassVerification::STATUS_INVALID)
            ->assertJsonPath('reason', 'The verification token is invalid.');

        $this->postJson(route('staff.boarding-pass-verifications.approve', $scanResponse->json('verification_id')))
            ->assertUnprocessable()
            ->assertJsonPath('status', BoardingPassVerification::STATUS_INVALID);

        $this->assertDatabaseHas('boarding_passes', [
            'id' => $boardingPass->getKey(),
            'pass_status' => BoardingPass::STATUS_ACTIVE,
            'used_at' => null,
        ]);
        $this->assertDatabaseHas('boarding_pass_verifications', [
            'id' => $scanResponse->json('verification_id'),
            'status' => BoardingPassVerification::STATUS_INVALID,
            'approved_at' => null,
        ]);
    }

    private function issuePass(User $passenger): BoardingPass
    {
        $flight = Flight::factory()->create();
        $booking = $passenger->bookings()->create([
            'booking_reference' => 'DBP-'.strtoupper((string) Str::ulid()),
            'flight_id' => $flight->getKey(),
            'booking_status' => Booking::STATUS_CONFIRMED,
            'booking_date' => now(),
            'number_of_passengers' => 1,
        ]);

        $this->actingAs($passenger)
            ->post(route('check-ins.store', $booking), ['confirmation' => '1'])
            ->assertRedirect();

        return BoardingPass::query()
            ->where('passenger_id', $passenger->getKey())
            ->latest('id')
            ->firstOrFail();
    }

    private function payloadFor(BoardingPass $boardingPass): string
    {
        return implode('|', [
            BoardingPass::QR_FORMAT_VERSION,
            $boardingPass->pass_reference,
            $boardingPass->verification_token,
        ]);
    }
}
