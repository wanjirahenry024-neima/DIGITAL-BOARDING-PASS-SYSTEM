<?php

namespace Tests\Feature;

use App\Models\Flight;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('security')]
class FlightManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_and_view_a_flight(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)
            ->post(route('admin.flights.store'), $this->flightData())
            ->assertRedirect();

        $flight = Flight::where('flight_number', 'NC-201')->firstOrFail();
        $response->assertRedirect(route('admin.flights.show', $flight));

        $this->assertDatabaseHas('flights', [
            'id' => $flight->id,
            'departure_airport' => 'RIV',
            'arrival_airport' => 'LKV',
            'total_seats' => 72,
            'available_seats' => 48,
            'status' => Flight::STATUS_SCHEDULED,
        ]);

        $this->get(route('admin.flights.show', $flight))
            ->assertOk()
            ->assertSee('NC-201')
            ->assertSee('RIV')
            ->assertSee('LKV');
    }

    public function test_admin_can_update_flight_details_and_status(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $flight = Flight::factory()->create();
        $updated = [...$this->flightData(), 'flight_number' => $flight->flight_number, 'status' => Flight::STATUS_BOARDING];

        $this->actingAs($admin)
            ->put(route('admin.flights.update', $flight), $updated)
            ->assertRedirect(route('admin.flights.show', $flight));

        $this->assertDatabaseHas('flights', [
            'id' => $flight->id,
            'airline' => 'North Coast Air',
            'status' => Flight::STATUS_BOARDING,
        ]);

        $this->patch(route('admin.flights.status', $flight), ['status' => Flight::STATUS_DEPARTED])
            ->assertRedirect();

        $this->assertDatabaseHas('flights', [
            'id' => $flight->id,
            'status' => Flight::STATUS_DEPARTED,
        ]);
    }

    public function test_admin_can_deactivate_a_flight(): void
    {
        $flight = Flight::factory()->create();

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->delete(route('admin.flights.destroy', $flight))
            ->assertRedirect(route('admin.flights.index'));

        $this->assertSoftDeleted($flight);
        $this->get(route('admin.flights.show', $flight->id))->assertNotFound();
    }

    public function test_admin_flight_list_supports_search_status_date_and_pagination(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $matchingFlight = Flight::factory()->create([
            'flight_number' => 'NC-201',
            'departure_date' => now()->addDays(2)->toDateString(),
            'status' => Flight::STATUS_SCHEDULED,
        ]);
        Flight::factory()->create([
            'flight_number' => 'NC-305',
            'departure_date' => now()->addDays(3)->toDateString(),
            'status' => Flight::STATUS_BOARDING,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.flights.index', [
                'q' => 'NC-201',
                'status' => Flight::STATUS_SCHEDULED,
                'departure_date' => $matchingFlight->departure_date->format('Y-m-d'),
            ]))
            ->assertOk()
            ->assertSee('NC-201')
            ->assertDontSee('NC-305');

        Flight::factory()->count(15)->create();

        $this->get(route('admin.flights.index'))
            ->assertOk()
            ->assertViewHas('flights', fn ($flights): bool => $flights->count() === 15 && $flights->total() === 17);
    }

    public function test_admin_create_rejects_duplicate_flight_number(): void
    {
        Flight::factory()->create(['flight_number' => 'NC-201']);

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->from(route('admin.flights.create'))
            ->post(route('admin.flights.store'), $this->flightData())
            ->assertRedirect(route('admin.flights.create'))
            ->assertSessionHasErrors('flight_number');
    }

    public static function negativeSeatCounts(): array
    {
        return [
            'negative total seats' => [-1, 0, ['total_seats', 'available_seats']],
            'negative available seats' => [72, -1, ['available_seats']],
        ];
    }

    #[DataProvider('negativeSeatCounts')]
    public function test_admin_create_rejects_negative_seat_counts(int $totalSeats, int $availableSeats, array $expectedErrors): void
    {
        $flightData = [
            ...$this->flightData(),
            'total_seats' => $totalSeats,
            'available_seats' => $availableSeats,
        ];

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->from(route('admin.flights.create'))
            ->post(route('admin.flights.store'), $flightData)
            ->assertRedirect(route('admin.flights.create'))
            ->assertSessionHasErrors($expectedErrors);
    }

    public function test_admin_create_rejects_available_seats_above_total(): void
    {
        $flightData = [...$this->flightData(), 'available_seats' => 73];

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->from(route('admin.flights.create'))
            ->post(route('admin.flights.store'), $flightData)
            ->assertRedirect(route('admin.flights.create'))
            ->assertSessionHasErrors('available_seats');
    }

    public function test_admin_create_rejects_same_departure_and_arrival_airport(): void
    {
        $flightData = [...$this->flightData(), 'arrival_airport' => 'RIV'];

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->from(route('admin.flights.create'))
            ->post(route('admin.flights.store'), $flightData)
            ->assertRedirect(route('admin.flights.create'))
            ->assertSessionHasErrors('departure_airport');
    }

    public function test_passenger_search_filters_available_flights_by_route_and_date(): void
    {
        $travelDate = now()->addDays(2)->toDateString();
        Flight::factory()->create([
            'flight_number' => 'NC-201',
            'departure_airport' => 'RIV',
            'arrival_airport' => 'LKV',
            'departure_date' => $travelDate,
            'available_seats' => 12,
            'status' => Flight::STATUS_SCHEDULED,
        ]);
        Flight::factory()->create([
            'flight_number' => 'NC-202',
            'departure_airport' => 'RIV',
            'arrival_airport' => 'MTN',
            'departure_date' => $travelDate,
        ]);
        Flight::factory()->create([
            'flight_number' => 'NC-203',
            'departure_airport' => 'RIV',
            'arrival_airport' => 'LKV',
            'departure_date' => $travelDate,
            'available_seats' => 0,
        ]);

        $this->actingAs(User::factory()->create(['role' => 'passenger']))
            ->get(route('flights.index', [
                'departure_airport' => 'RIV',
                'arrival_airport' => 'LKV',
                'departure_date' => $travelDate,
            ]))
            ->assertOk()
            ->assertSee('NC-201')
            ->assertDontSee('NC-202')
            ->assertDontSee('NC-203');
    }

    public function test_passenger_cannot_view_unavailable_or_cancelled_flight(): void
    {
        $flight = Flight::factory()->create(['status' => Flight::STATUS_CANCELLED]);

        $this->actingAs(User::factory()->create(['role' => 'passenger']))
            ->get(route('flights.show', $flight))
            ->assertNotFound();
    }

    public function test_non_admin_roles_cannot_manage_flights(): void
    {
        foreach (['passenger', 'staff'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get(route('admin.flights.index'))
                ->assertForbidden();
        }
    }

    public function test_staff_cannot_access_passenger_flight_search(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'staff']))
            ->get(route('flights.index'))
            ->assertForbidden();
    }

    public function test_guest_cannot_access_flight_management_or_search(): void
    {
        $this->get(route('admin.flights.index'))->assertRedirect(route('login'));
        $this->get(route('flights.index'))->assertRedirect(route('login'));
    }

    /** @return array<string, mixed> */
    private function flightData(): array
    {
        return [
            'flight_number' => 'NC-201',
            'airline' => 'North Coast Air',
            'departure_airport' => 'RIV',
            'arrival_airport' => 'LKV',
            'departure_date' => now()->addDays(2)->toDateString(),
            'departure_time' => '08:30',
            'arrival_time' => '09:45',
            'aircraft' => 'ATR 72',
            'total_seats' => 72,
            'available_seats' => 48,
            'status' => Flight::STATUS_SCHEDULED,
        ];
    }
}
