<?php

namespace Tests\Feature;

use App\Models\BoardingPass;
use App\Models\Booking;
use App\Models\CheckIn;
use App\Models\Flight;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('security')]
class BoardingPassManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_successful_check_in_issues_one_active_boarding_pass(): void
    {
        $passenger = User::factory()->create(['role' => 'passenger']);
        $flight = Flight::factory()->create([
            'flight_number' => 'NC-742',
            'departure_airport' => 'RIV',
            'arrival_airport' => 'LKV',
            'departure_date' => '2026-10-16',
            'departure_time' => '09:00:00',
        ]);
        $booking = $this->createBooking($passenger, $flight);

        $this->assertDatabaseCount('check_ins', 0);
        $this->assertDatabaseCount('boarding_passes', 0);

        $this->actingAs($passenger)
            ->post(route('check-ins.store', $booking), ['confirmation' => '1'])
            ->assertRedirect();

        $checkIn = CheckIn::query()->firstOrFail();
        $boardingPass = BoardingPass::query()->sole();

        $this->assertSame($checkIn->getKey(), $boardingPass->check_in_id);
        $this->assertSame($booking->getKey(), $boardingPass->booking_id);
        $this->assertSame($passenger->getKey(), $boardingPass->passenger_id);
        $this->assertSame($flight->getKey(), $boardingPass->flight_id);
        $this->assertSame($checkIn->seat_number, $boardingPass->seat_number);
        $this->assertSame(BoardingPass::STATUS_ACTIVE, $boardingPass->pass_status);
        $this->assertSame('2026-10-16 09:00:00', $boardingPass->expires_at->format('Y-m-d H:i:s'));
        $this->assertMatchesRegularExpression('/^BP-[0-9A-HJKMNP-TV-Z]{26}$/', $boardingPass->pass_reference);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $boardingPass->verification_token);
        $this->assertSame(hash('sha256', $boardingPass->verification_token), $boardingPass->verification_token_hash);
        $this->assertNotSame(
            $boardingPass->verification_token,
            DB::table('boarding_passes')->where('id', $boardingPass->getKey())->value('verification_token'),
        );
        $this->assertArrayNotHasKey('verification_token', $boardingPass->toArray());
        $this->assertArrayNotHasKey('verification_token_hash', $boardingPass->toArray());

        $this->actingAs($passenger)
            ->get(route('boarding-passes.show', $boardingPass))
            ->assertOk()
            ->assertSee($passenger->full_name)
            ->assertSee($flight->flight_number)
            ->assertSee('RIV')
            ->assertSee('LKV')
            ->assertSee('Oct 16, 2026')
            ->assertSee('09:00')
            ->assertSee($boardingPass->seat_number)
            ->assertSee($boardingPass->pass_reference)
            ->assertSee(BoardingPass::STATUS_ACTIVE)
            ->assertSee('data:image/svg+xml;base64,', escape: false)
            ->assertSee('Secure boarding pass verification QR code')
            ->assertSee('Print / Save as PDF');
    }

    public function test_passenger_can_download_a_printable_boarding_pass(): void
    {
        $passenger = User::factory()->create(['role' => 'passenger']);
        $flight = Flight::factory()->create();
        $booking = $this->createBooking($passenger, $flight);
        $this->actingAs($passenger)
            ->post(route('check-ins.store', $booking), ['confirmation' => '1'])
            ->assertRedirect();
        $boardingPass = BoardingPass::query()->firstOrFail();

        $response = $this->get(route('boarding-passes.download', $boardingPass));

        $response->assertDownload(Str::slug('boarding-pass-'.$boardingPass->pass_reference).'.html');
        $this->assertStringContainsString($boardingPass->pass_reference, $response->streamedContent());
        $this->assertStringContainsString('data:image/svg+xml;base64,', $response->streamedContent());
    }

    public function test_passenger_cannot_view_or_download_another_passengers_boarding_pass(): void
    {
        $owner = User::factory()->create(['role' => 'passenger']);
        $otherPassenger = User::factory()->create(['role' => 'passenger']);
        $booking = $this->createBooking($owner, Flight::factory()->create());

        $this->actingAs($owner)
            ->post(route('check-ins.store', $booking), ['confirmation' => '1'])
            ->assertRedirect();
        $boardingPass = BoardingPass::query()->firstOrFail();

        $this->actingAs($otherPassenger)
            ->get(route('boarding-passes.show', $boardingPass))
            ->assertNotFound();

        $this->get(route('boarding-passes.download', $boardingPass))
            ->assertNotFound();
    }

    public function test_duplicate_boarding_pass_for_a_check_in_is_rejected_by_the_database(): void
    {
        $passenger = User::factory()->create(['role' => 'passenger']);
        $flight = Flight::factory()->create();
        $booking = $this->createBooking($passenger, $flight);
        $this->actingAs($passenger)
            ->post(route('check-ins.store', $booking), ['confirmation' => '1'])
            ->assertRedirect();
        $boardingPass = BoardingPass::query()->firstOrFail();

        $this->expectException(QueryException::class);

        BoardingPass::create([
            'pass_reference' => 'BP-DUPLICATE-001',
            'booking_id' => $boardingPass->booking_id,
            'passenger_id' => $boardingPass->passenger_id,
            'flight_id' => $boardingPass->flight_id,
            'check_in_id' => $boardingPass->check_in_id,
            'seat_number' => $boardingPass->seat_number,
            'pass_status' => BoardingPass::STATUS_ACTIVE,
            'issued_at' => now(),
            'expires_at' => $boardingPass->expires_at,
        ]);
    }

    public function test_failed_check_in_does_not_issue_a_boarding_pass(): void
    {
        $passenger = User::factory()->create(['role' => 'passenger']);
        $booking = $this->createBooking(
            $passenger,
            Flight::factory()->create(),
            ['booking_status' => Booking::STATUS_CANCELLED],
        );

        $this->actingAs($passenger)
            ->post(route('check-ins.store', $booking), ['confirmation' => '1'])
            ->assertSessionHasErrors('booking');

        $this->assertDatabaseCount('check_ins', 0);
        $this->assertDatabaseCount('boarding_passes', 0);
    }

    private function createBooking(User $passenger, Flight $flight, array $attributes = []): Booking
    {
        return $passenger->bookings()->create([
            'booking_reference' => 'DBP-'.strtoupper((string) Str::ulid()),
            'flight_id' => $flight->getKey(),
            'booking_status' => Booking::STATUS_CONFIRMED,
            'booking_date' => now(),
            'number_of_passengers' => 1,
            ...$attributes,
        ]);
    }
}
