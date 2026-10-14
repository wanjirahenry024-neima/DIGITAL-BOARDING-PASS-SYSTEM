<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Flight;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class AuditLoggingTest extends TestCase
{
    use RefreshDatabase;

    public function test_successful_and_failed_authentication_and_logout_are_audited(): void
    {
        $user = User::factory()->create(['password' => 'password']);

        $this->post('/login', [
            'email' => 'missing@example.com',
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');
        $this->assertDatabaseHas('audit_logs', [
            'event' => AuditLog::EVENT_LOGIN_FAILED,
            'actor_id' => null,
        ]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('dashboard'));
        $this->assertDatabaseHas('audit_logs', [
            'event' => AuditLog::EVENT_LOGIN,
            'actor_id' => $user->getKey(),
            'subject_id' => $user->getKey(),
        ]);

        $this->post('/logout')->assertRedirect(route('home'));
        $this->assertDatabaseHas('audit_logs', [
            'event' => AuditLog::EVENT_LOGOUT,
            'actor_id' => $user->getKey(),
        ]);
        $this->assertDatabaseCount('audit_logs', 3);
    }

    public function test_booking_creation_and_cancellation_are_audited(): void
    {
        $passenger = User::factory()->create(['role' => 'passenger']);
        $flight = Flight::factory()->create();

        $this->actingAs($passenger)
            ->post(route('bookings.store'), [
                'flight_id' => $flight->getKey(),
                'number_of_passengers' => 1,
            ])
            ->assertRedirect();

        $booking = $passenger->bookings()->firstOrFail();
        $this->assertDatabaseHas('audit_logs', [
            'event' => AuditLog::EVENT_BOOKING_CREATED,
            'actor_id' => $passenger->getKey(),
            'subject_type' => $booking->getMorphClass(),
            'subject_id' => $booking->getKey(),
        ]);

        $this->patch(route('bookings.cancel', $booking))->assertRedirect();
        $this->assertDatabaseHas('audit_logs', [
            'event' => AuditLog::EVENT_BOOKING_CANCELLED,
            'actor_id' => $passenger->getKey(),
            'subject_id' => $booking->getKey(),
        ]);
    }

    public function test_administrator_changes_are_audited(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $flightData = [
            'flight_number' => 'NC-601',
            'airline' => 'North Coast Air',
            'departure_airport' => 'RIV',
            'arrival_airport' => 'LKV',
            'departure_date' => now()->addDays(3)->toDateString(),
            'departure_time' => '09:00',
            'arrival_time' => '10:00',
            'aircraft' => 'ATR 72',
            'total_seats' => 72,
            'available_seats' => 60,
            'status' => Flight::STATUS_SCHEDULED,
        ];

        $this->actingAs($admin)
            ->post(route('admin.flights.store'), $flightData)
            ->assertRedirect();

        $flight = Flight::query()->where('flight_number', 'NC-601')->firstOrFail();
        $this->put(route('admin.flights.update', $flight), [
            ...$flightData,
            'airline' => 'North Coast Airlines',
        ])->assertRedirect();
        $this->patch(route('admin.flights.status', $flight), [
            'status' => Flight::STATUS_BOARDING,
        ])->assertRedirect();
        $this->delete(route('admin.flights.destroy', $flight))->assertRedirect();

        $flightActions = AuditLog::query()
            ->where('event', AuditLog::EVENT_ADMINISTRATOR_CHANGE)
            ->where('subject_type', Flight::class)
            ->where('subject_id', $flight->getKey())
            ->get()
            ->pluck('properties.action')
            ->all();
        $this->assertSame([
            'flight_created',
            'flight_updated',
            'flight_status_updated',
            'flight_deactivated',
        ], $flightActions);

        $passenger = User::factory()->create(['role' => 'passenger']);
        $adminBooking = $passenger->bookings()->create([
            'booking_reference' => 'DBP-ADMIN-CANCEL-001',
            'flight_id' => Flight::factory()->create()->getKey(),
            'booking_status' => Booking::STATUS_CONFIRMED,
            'booking_date' => now(),
            'number_of_passengers' => 1,
        ]);

        $this->patch(route('admin.bookings.cancel', $adminBooking))->assertRedirect();
        $this->assertDatabaseHas('audit_logs', [
            'event' => AuditLog::EVENT_ADMINISTRATOR_CHANGE,
            'actor_id' => $admin->getKey(),
            'subject_type' => Booking::class,
            'subject_id' => $adminBooking->getKey(),
        ]);
    }

    public function test_only_administrators_can_view_audit_logs(): void
    {
        AuditLog::create([
            'event' => AuditLog::EVENT_BOOKING_CREATED,
            'description' => 'Booking created.',
            'occurred_at' => now(),
        ]);

        $this->get(route('admin.audit-logs.index'))->assertRedirect(route('login'));
        foreach (['passenger', 'staff'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get(route('admin.audit-logs.index'))
                ->assertForbidden();
        }

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('admin.audit-logs.index'))
            ->assertOk()
            ->assertSee('Audit logs')
            ->assertSee('booking created')
            ->assertSee('Booking created.');
    }

    public function test_audit_log_cannot_be_updated(): void
    {
        $auditLog = AuditLog::create([
            'event' => AuditLog::EVENT_LOGIN,
            'occurred_at' => now(),
        ]);

        $this->expectException(LogicException::class);

        $auditLog->update(['description' => 'Edited']);
    }

    public function test_audit_log_cannot_be_deleted(): void
    {
        $auditLog = AuditLog::create([
            'event' => AuditLog::EVENT_LOGIN,
            'occurred_at' => now(),
        ]);

        $this->expectException(LogicException::class);

        $auditLog->delete();
    }
}
