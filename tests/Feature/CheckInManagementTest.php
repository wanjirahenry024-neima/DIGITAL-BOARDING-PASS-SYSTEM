<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\CheckIn;
use App\Models\Flight;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('security')]
class CheckInManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_passenger_can_review_and_confirm_check_in(): void
    {
        $passenger = User::factory()->create(['role' => 'passenger']);
        $flight = Flight::factory()->create();
        $booking = $this->createBooking($passenger, $flight);

        $this->actingAs($passenger)
            ->get(route('bookings.index'))
            ->assertOk()
            ->assertSee($booking->booking_reference);

        $this->get(route('check-ins.create', $booking))
            ->assertOk()
            ->assertSee($passenger->full_name)
            ->assertSee($passenger->email)
            ->assertSee($flight->flight_number)
            ->assertSee($flight->departure_airport)
            ->assertSee($flight->arrival_airport);

        $response = $this->post(route('check-ins.store', $booking), [
            'confirmation' => '1',
        ]);
        $checkIn = CheckIn::query()->firstOrFail();

        $response->assertRedirect(route('check-ins.confirmation', $checkIn));
        $this->assertSame($booking->getKey(), $checkIn->booking_id);
        $this->assertSame($passenger->getKey(), $checkIn->passenger_id);
        $this->assertSame($flight->getKey(), $checkIn->flight_id);
        $this->assertSame('001', $checkIn->seat_number);
        $this->assertSame(CheckIn::STATUS_CHECKED_IN, $checkIn->status);
        $this->assertMatchesRegularExpression('/^CI-[0-9A-HJKMNP-TV-Z]{26}$/', $checkIn->check_in_reference);
        $this->assertDatabaseHas('boarding_passes', [
            'check_in_id' => $checkIn->getKey(),
            'booking_id' => $booking->getKey(),
            'pass_status' => 'ACTIVE',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'event' => AuditLog::EVENT_CHECK_IN,
            'actor_id' => $passenger->getKey(),
            'subject_id' => $checkIn->getKey(),
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'event' => AuditLog::EVENT_BOARDING_PASS_CREATED,
            'actor_id' => $passenger->getKey(),
            'subject_id' => $checkIn->boardingPass->getKey(),
        ]);

        $this->get(route('check-ins.confirmation', $checkIn))
            ->assertOk()
            ->assertSee($checkIn->check_in_reference)
            ->assertSee($checkIn->seat_number)
            ->assertSee('View your boarding pass');
    }

    public function test_check_in_assigns_a_different_seat_to_each_booking_on_a_flight(): void
    {
        $flight = Flight::factory()->create(['total_seats' => 2]);
        $firstPassenger = User::factory()->create(['role' => 'passenger']);
        $secondPassenger = User::factory()->create(['role' => 'passenger']);
        $firstBooking = $this->createBooking($firstPassenger, $flight);
        $secondBooking = $this->createBooking($secondPassenger, $flight);

        $this->actingAs($firstPassenger)
            ->post(route('check-ins.store', $firstBooking), ['confirmation' => '1'])
            ->assertRedirect();

        $this->actingAs($secondPassenger)
            ->post(route('check-ins.store', $secondBooking), ['confirmation' => '1'])
            ->assertRedirect();

        $this->assertSame(
            ['001', '002'],
            CheckIn::query()->orderBy('seat_number')->pluck('seat_number')->all(),
        );
    }

    public function test_passenger_cannot_check_in_the_same_booking_twice(): void
    {
        $passenger = User::factory()->create(['role' => 'passenger']);
        $booking = $this->createBooking($passenger, Flight::factory()->create());
        $this->actingAs($passenger)
            ->post(route('check-ins.store', $booking), ['confirmation' => '1'])
            ->assertRedirect();

        $this->post(route('check-ins.store', $booking), ['confirmation' => '1'])
            ->assertSessionHasErrors('booking');

        $this->assertDatabaseCount('check_ins', 1);
        $this->assertDatabaseCount('boarding_passes', 1);
    }

    public function test_check_in_confirmation_is_required(): void
    {
        $passenger = User::factory()->create(['role' => 'passenger']);
        $booking = $this->createBooking($passenger, Flight::factory()->create());

        $this->actingAs($passenger)
            ->post(route('check-ins.store', $booking), [])
            ->assertSessionHasErrors([
                'confirmation' => 'Please confirm that you want to check in.',
            ]);

        $this->assertDatabaseCount('check_ins', 0);
    }

    public function test_passenger_cannot_check_in_a_cancelled_booking(): void
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
    }

    public function test_passenger_cannot_check_in_a_cancelled_or_deactivated_flight(): void
    {
        $passenger = User::factory()->create(['role' => 'passenger']);

        foreach ([
            Flight::factory()->create(['status' => Flight::STATUS_CANCELLED]),
            Flight::factory()->create(),
        ] as $index => $flight) {
            if ($index === 1) {
                $flight->delete();
            }

            $booking = $this->createBooking($passenger, $flight);

            $this->actingAs($passenger)
                ->post(route('check-ins.store', $booking), ['confirmation' => '1'])
                ->assertSessionHasErrors('booking');
        }

        $this->assertDatabaseCount('check_ins', 0);
    }

    public function test_passenger_cannot_check_in_a_flight_that_is_no_longer_accepting_check_in(): void
    {
        $passenger = User::factory()->create(['role' => 'passenger']);

        foreach ([Flight::STATUS_DEPARTED, Flight::STATUS_COMPLETED] as $status) {
            $booking = $this->createBooking(
                $passenger,
                Flight::factory()->create(['status' => $status]),
            );

            $this->actingAs($passenger)
                ->post(route('check-ins.store', $booking), ['confirmation' => '1'])
                ->assertSessionHasErrors('booking');
        }

        $this->assertDatabaseCount('check_ins', 0);
    }

    public function test_check_in_is_allowed_at_the_one_hour_deadline(): void
    {
        $this->travelTo('2026-10-14 10:00:00');
        $passenger = User::factory()->create(['role' => 'passenger']);
        $flight = Flight::factory()->create([
            'departure_date' => '2026-10-14',
            'departure_time' => '11:00:00',
        ]);
        $booking = $this->createBooking($passenger, $flight);

        $this->actingAs($passenger)
            ->post(route('check-ins.store', $booking), ['confirmation' => '1'])
            ->assertRedirect();

        $this->assertDatabaseCount('check_ins', 1);
    }

    public function test_passenger_cannot_check_in_after_the_one_hour_deadline(): void
    {
        $this->travelTo('2026-10-14 10:00:01');
        $passenger = User::factory()->create(['role' => 'passenger']);
        $flight = Flight::factory()->create([
            'departure_date' => '2026-10-14',
            'departure_time' => '11:00:00',
        ]);
        $booking = $this->createBooking($passenger, $flight);

        $this->actingAs($passenger)
            ->post(route('check-ins.store', $booking), ['confirmation' => '1'])
            ->assertSessionHasErrors([
                'booking' => 'Check-in closes one hour before departure.',
            ]);

        $this->assertDatabaseCount('check_ins', 0);
    }

    public function test_passenger_cannot_check_in_another_passengers_booking_or_confirmation(): void
    {
        $owner = User::factory()->create(['role' => 'passenger']);
        $otherPassenger = User::factory()->create(['role' => 'passenger']);
        $booking = $this->createBooking($owner, Flight::factory()->create());

        $this->actingAs($otherPassenger)
            ->get(route('check-ins.create', $booking))
            ->assertNotFound();

        $this->post(route('check-ins.store', $booking), ['confirmation' => '1'])
            ->assertNotFound();

        $this->assertDatabaseCount('check_ins', 0);
    }

    public function test_passenger_cannot_view_another_passengers_check_in_confirmation(): void
    {
        $owner = User::factory()->create(['role' => 'passenger']);
        $otherPassenger = User::factory()->create(['role' => 'passenger']);
        $booking = $this->createBooking($owner, Flight::factory()->create());

        $this->actingAs($owner)
            ->post(route('check-ins.store', $booking), ['confirmation' => '1'])
            ->assertRedirect();
        $checkIn = CheckIn::query()->firstOrFail();

        $this->actingAs($otherPassenger)
            ->get(route('check-ins.confirmation', $checkIn))
            ->assertNotFound();
    }

    public function test_only_active_passengers_can_check_in(): void
    {
        $flight = Flight::factory()->create();
        $booking = $this->createBooking(User::factory()->create(), $flight);

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('check-ins.create', $booking))
            ->assertForbidden();

        $inactivePassenger = User::factory()->create([
            'role' => 'passenger',
            'status' => 'inactive',
        ]);
        $inactiveBooking = $this->createBooking($inactivePassenger, $flight);

        $this->actingAs($inactivePassenger)
            ->post(route('check-ins.store', $inactiveBooking), ['confirmation' => '1'])
            ->assertForbidden();

        $this->assertDatabaseCount('check_ins', 0);
    }

    public function test_guest_cannot_access_check_in_pages_or_submit_check_in(): void
    {
        $booking = $this->createBooking(
            User::factory()->create(['role' => 'passenger']),
            Flight::factory()->create(),
        );

        $this->get(route('check-ins.create', $booking))
            ->assertRedirect(route('login'));

        $this->post(route('check-ins.store', $booking), ['confirmation' => '1'])
            ->assertRedirect(route('login'));

        $this->assertDatabaseCount('check_ins', 0);
    }

    public function test_checked_in_booking_cannot_be_cancelled(): void
    {
        $passenger = User::factory()->create(['role' => 'passenger']);
        $flight = Flight::factory()->create();
        $booking = $this->createBooking($passenger, $flight);

        $this->actingAs($passenger)
            ->post(route('check-ins.store', $booking), ['confirmation' => '1'])
            ->assertRedirect();

        $this->get(route('bookings.show', $booking))
            ->assertOk()
            ->assertDontSee('Cancel booking');

        $this->patch(route('bookings.cancel', $booking))
            ->assertSessionHasErrors('booking');

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->getKey(),
            'booking_status' => Booking::STATUS_CONFIRMED,
        ]);
        $this->assertDatabaseCount('check_ins', 1);
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
