<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\BoardingPass;
use App\Models\BoardingPassVerification;
use App\Models\Booking;
use App\Models\Flight;
use App\Models\User;
use App\Models\VerificationRecord;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('security')]
class BoardingPassVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_scan_records_a_valid_attempt_until_boarding_is_approved(): void
    {
        $passenger = User::factory()->create(['role' => 'passenger']);
        $staff = User::factory()->create(['role' => 'staff']);
        $flight = Flight::factory()->create(['flight_number' => 'NC-742']);
        $boardingPass = $this->issuePass($passenger, $flight);
        $payload = $this->payloadFor($boardingPass);

        $scanResponse = $this->actingAs($staff)
            ->postJson(route('staff.boarding-passes.verify'), [
                'payload' => $payload,
                'gate' => 'A4',
                'device_identifier' => 'GATE-TABLET-01',
            ])
            ->assertOk()
            ->assertJson([
                'status' => BoardingPassVerification::STATUS_VALID,
                'passenger' => $passenger->full_name,
                'flight' => 'NC-742',
                'destination' => $flight->arrival_airport,
                'seat' => $boardingPass->seat_number,
                'boarding_pass_id' => $boardingPass->pass_reference,
                'can_approve' => true,
            ])
            ->assertJsonMissingPath('passenger_email');
        $verificationId = $scanResponse->json('verification_id');

        $this->assertDatabaseHas('boarding_passes', [
            'id' => $boardingPass->getKey(),
            'pass_status' => BoardingPass::STATUS_ACTIVE,
            'used_at' => null,
        ]);
        $this->assertDatabaseHas('boarding_pass_verifications', [
            'id' => $verificationId,
            'staff_id' => $staff->getKey(),
            'boarding_pass_id' => $boardingPass->getKey(),
            'status' => BoardingPassVerification::STATUS_VALID,
        ]);
        $this->assertDatabaseHas('verification_records', [
            'id' => $scanResponse->json('verification_record_id'),
            'boarding_pass_id' => $boardingPass->getKey(),
            'passenger_id' => $passenger->getKey(),
            'flight_id' => $flight->getKey(),
            'staff_id' => $staff->getKey(),
            'verification_reference' => $boardingPass->pass_reference,
            'result' => VerificationRecord::RESULT_VALID,
            'verification_method' => VerificationRecord::METHOD_ONLINE,
            'gate' => 'A4',
            'device_identifier' => 'GATE-TABLET-01',
        ]);
        $verificationRecord = VerificationRecord::query()->findOrFail($scanResponse->json('verification_record_id'));
        $this->assertArrayNotHasKey('verification_token', $verificationRecord->getAttributes());
        $qrAuditLog = AuditLog::query()
            ->where('event', AuditLog::EVENT_QR_VERIFICATION)
            ->firstOrFail();
        $storedVerificationData = json_encode([
            $verificationRecord->getAttributes(),
            $qrAuditLog->properties,
        ], JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString($boardingPass->verification_token, $storedVerificationData);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'qr_verification',
            'actor_id' => $staff->getKey(),
            'subject_type' => BoardingPass::class,
            'subject_id' => $boardingPass->getKey(),
        ]);

        $this->postJson(route('staff.boarding-pass-verifications.approve', $verificationId))
            ->assertOk()
            ->assertJson([
                'status' => BoardingPassVerification::STATUS_USED,
                'can_approve' => false,
                'approved' => true,
            ]);

        $this->assertDatabaseHas('boarding_passes', [
            'id' => $boardingPass->getKey(),
            'pass_status' => BoardingPass::STATUS_USED,
        ]);
        $this->assertNotNull($boardingPass->fresh()->used_at);
        $this->assertDatabaseHas('boarding_pass_verifications', [
            'id' => $verificationId,
            'approved_by' => $staff->getKey(),
            'status' => BoardingPassVerification::STATUS_USED,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'boarding_approved',
            'actor_id' => $staff->getKey(),
            'subject_type' => BoardingPass::class,
            'subject_id' => $boardingPass->getKey(),
        ]);

        $usedScan = $this->postJson(route('staff.boarding-passes.verify'), ['payload' => $payload])
            ->assertOk()
            ->assertJsonPath('status', BoardingPassVerification::STATUS_USED);
        $this->assertDatabaseHas('verification_records', [
            'id' => $usedScan->json('verification_record_id'),
            'result' => VerificationRecord::RESULT_USED,
            'verification_reference' => $boardingPass->pass_reference,
        ]);
        $this->assertDatabaseCount('verification_records', 2);
    }

    public function test_invalid_token_does_not_consume_or_change_an_active_pass(): void
    {
        $passenger = User::factory()->create(['role' => 'passenger']);
        $staff = User::factory()->create(['role' => 'staff']);
        $boardingPass = $this->issuePass($passenger, Flight::factory()->create());
        $payload = BoardingPass::QR_FORMAT_VERSION.'|'.$boardingPass->pass_reference.'|'.str_repeat('0', 64);

        $this->actingAs($staff)
            ->postJson(route('staff.boarding-passes.verify'), ['payload' => $payload])
            ->assertOk()
            ->assertJsonPath('status', BoardingPassVerification::STATUS_INVALID)
            ->assertJsonPath('reason', 'The verification token is invalid.')
            ->assertJsonPath('passenger', null);

        $this->assertDatabaseHas('verification_records', [
            'boarding_pass_id' => $boardingPass->getKey(),
            'staff_id' => $staff->getKey(),
            'verification_reference' => $boardingPass->pass_reference,
            'result' => VerificationRecord::RESULT_INVALID,
            'verification_method' => VerificationRecord::METHOD_ONLINE,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'failed_verification',
            'actor_id' => $staff->getKey(),
            'subject_id' => $boardingPass->getKey(),
        ]);
        $this->assertDatabaseHas('boarding_passes', [
            'id' => $boardingPass->getKey(),
            'pass_status' => BoardingPass::STATUS_ACTIVE,
            'used_at' => null,
        ]);
        $this->assertDatabaseHas('boarding_pass_verifications', [
            'status' => BoardingPassVerification::STATUS_INVALID,
            'staff_id' => $staff->getKey(),
        ]);
    }

    public function test_expired_qr_payload_marks_the_pass_expired(): void
    {
        $passenger = User::factory()->create(['role' => 'passenger']);
        $staff = User::factory()->create(['role' => 'staff']);
        $boardingPass = $this->issuePass($passenger, Flight::factory()->create());
        $payload = $this->payloadFor($boardingPass);
        $this->travelTo($boardingPass->expires_at->copy()->addSecond());

        $this->actingAs($staff)
            ->postJson(route('staff.boarding-passes.verify'), ['payload' => $payload])
            ->assertOk()
            ->assertJsonPath('status', BoardingPassVerification::STATUS_EXPIRED);

        $this->assertDatabaseHas('boarding_passes', [
            'id' => $boardingPass->getKey(),
            'pass_status' => BoardingPass::STATUS_EXPIRED,
            'used_at' => null,
        ]);
    }

    public function test_cancelled_booking_cancels_its_active_boarding_pass(): void
    {
        $passenger = User::factory()->create(['role' => 'passenger']);
        $staff = User::factory()->create(['role' => 'staff']);
        $boardingPass = $this->issuePass($passenger, Flight::factory()->create());
        $boardingPass->booking->update(['booking_status' => Booking::STATUS_CANCELLED]);

        $this->actingAs($staff)
            ->postJson(route('staff.boarding-passes.verify'), ['payload' => $this->payloadFor($boardingPass)])
            ->assertOk()
            ->assertJsonPath('status', BoardingPassVerification::STATUS_CANCELLED);

        $this->assertDatabaseHas('boarding_passes', [
            'id' => $boardingPass->getKey(),
            'pass_status' => BoardingPass::STATUS_CANCELLED,
            'used_at' => null,
        ]);
    }

    public function test_cancelled_flight_cancels_its_active_boarding_pass(): void
    {
        $passenger = User::factory()->create(['role' => 'passenger']);
        $staff = User::factory()->create(['role' => 'staff']);
        $flight = Flight::factory()->create();
        $boardingPass = $this->issuePass($passenger, $flight);
        $flight->update(['status' => Flight::STATUS_CANCELLED]);

        $this->actingAs($staff)
            ->postJson(route('staff.boarding-passes.verify'), ['payload' => $this->payloadFor($boardingPass)])
            ->assertOk()
            ->assertJsonPath('status', BoardingPassVerification::STATUS_CANCELLED);

        $this->assertDatabaseHas('boarding_passes', [
            'id' => $boardingPass->getKey(),
            'pass_status' => BoardingPass::STATUS_CANCELLED,
            'used_at' => null,
        ]);
    }

    public function test_verification_token_digest_cannot_be_assigned_to_a_second_pass(): void
    {
        $firstPassenger = User::factory()->create(['role' => 'passenger']);
        $firstPass = $this->issuePass($firstPassenger, Flight::factory()->create());
        $secondPassenger = User::factory()->create(['role' => 'passenger']);
        $secondPass = $this->issuePass($secondPassenger, Flight::factory()->create());

        $this->expectException(QueryException::class);

        $secondPass->update([
            'verification_token_hash' => $firstPass->verification_token_hash,
        ]);
    }

    public function test_malformed_qr_payload_is_recorded_as_a_failed_verification(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $this->actingAs($staff)
            ->postJson(route('staff.boarding-passes.verify'), ['payload' => 'passenger@example.com'])
            ->assertOk()
            ->assertJsonPath('status', BoardingPassVerification::STATUS_INVALID)
            ->assertJsonPath('reason', 'The QR verification payload is invalid.');

        $this->assertDatabaseCount('boarding_passes', 0);
        $this->assertDatabaseHas('boarding_pass_verifications', [
            'staff_id' => $staff->getKey(),
            'status' => BoardingPassVerification::STATUS_INVALID,
            'pass_reference' => null,
        ]);
        $this->assertDatabaseHas('verification_records', [
            'staff_id' => $staff->getKey(),
            'result' => VerificationRecord::RESULT_INVALID,
            'verification_reference' => null,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'failed_verification',
            'actor_id' => $staff->getKey(),
        ]);
    }

    private function issuePass(User $passenger, Flight $flight): BoardingPass
    {
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
