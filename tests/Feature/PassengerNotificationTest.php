<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\CheckIn;
use App\Models\Flight;
use App\Models\User;
use App\Notifications\PassengerUpdateNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class PassengerNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_sends_a_private_account_notification_through_database_and_mail(): void
    {
        Notification::fake();

        $this->post(route('register.store'), [
            'full_name' => 'Avery Passenger',
            'email' => 'avery-notification@example.com',
            'phone' => '+15551234567',
            'password' => 'secure-pass-123',
            'password_confirmation' => 'secure-pass-123',
        ])->assertRedirect(route('dashboard'));

        $passenger = User::query()->where('email', 'avery-notification@example.com')->firstOrFail();

        Notification::assertSentTo(
            $passenger,
            PassengerUpdateNotification::class,
            fn (PassengerUpdateNotification $notification, array $channels): bool => $notification->event === 'registration'
                && $notification->title === 'Your account is ready'
                && array_keys($notification->toArray($passenger)) === ['event', 'title', 'message', 'url']
                && in_array('database', $channels, true)
                && in_array('mail', $channels, true),
        );
    }

    public function test_booking_confirmation_and_cancellation_notify_the_passenger(): void
    {
        $passenger = User::factory()->create(['role' => 'passenger']);
        $flight = Flight::factory()->create();
        Notification::fake();

        $this->actingAs($passenger)
            ->post(route('bookings.store'), [
                'flight_id' => $flight->getKey(),
                'number_of_passengers' => 1,
            ])
            ->assertRedirect();

        $booking = Booking::query()->firstOrFail();
        Notification::assertSentTo(
            $passenger,
            PassengerUpdateNotification::class,
            fn (PassengerUpdateNotification $notification): bool => $notification->event === 'booking_confirmed'
                && str_contains($notification->message, $booking->booking_reference)
                && ! str_contains($notification->message, $passenger->email),
        );

        $this->patch(route('bookings.cancel', $booking))->assertRedirect();

        Notification::assertSentTo(
            $passenger,
            PassengerUpdateNotification::class,
            fn (PassengerUpdateNotification $notification): bool => $notification->event === 'booking_cancelled'
                && str_contains($notification->message, $booking->booking_reference),
        );
    }

    public function test_check_in_and_boarding_pass_issuance_each_notify_the_passenger(): void
    {
        $passenger = User::factory()->create(['role' => 'passenger']);
        $flight = Flight::factory()->create();
        $booking = $this->createBooking($passenger, $flight);
        Notification::fake();

        $this->actingAs($passenger)
            ->post(route('check-ins.store', $booking), ['confirmation' => '1'])
            ->assertRedirect();

        $checkIn = CheckIn::query()->firstOrFail();
        Notification::assertSentTo(
            $passenger,
            PassengerUpdateNotification::class,
            fn (PassengerUpdateNotification $notification): bool => $notification->event === 'check_in_completed'
                && str_contains($notification->message, $flight->flight_number),
        );
        Notification::assertSentTo(
            $passenger,
            PassengerUpdateNotification::class,
            fn (PassengerUpdateNotification $notification): bool => $notification->event === 'boarding_pass_issued'
                && $notification->url === route('boarding-passes.show', $checkIn->boardingPass),
        );
    }

    public function test_flight_status_changes_notify_only_passengers_with_confirmed_bookings(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $passenger = User::factory()->create(['role' => 'passenger']);
        $unbookedPassenger = User::factory()->create(['role' => 'passenger']);
        $flight = Flight::factory()->create();
        $this->createBooking($passenger, $flight);
        Notification::fake();

        $this->actingAs($admin)
            ->patch(route('admin.flights.status', $flight), ['status' => Flight::STATUS_BOARDING])
            ->assertRedirect();

        $this->patch(route('admin.flights.status', $flight), ['status' => Flight::STATUS_BOARDING])
            ->assertRedirect();

        Notification::assertSentTo(
            $passenger,
            PassengerUpdateNotification::class,
            fn (PassengerUpdateNotification $notification): bool => $notification->event === 'flight_status_changed'
                && str_contains($notification->message, Flight::STATUS_BOARDING),
        );
        Notification::assertNotSentTo($unbookedPassenger, PassengerUpdateNotification::class);
        Notification::assertSentToTimes($passenger, PassengerUpdateNotification::class, 1);
    }

    public function test_flight_details_edits_notify_passengers_only_when_status_changes(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $passenger = User::factory()->create(['role' => 'passenger']);
        $flight = Flight::factory()->create();
        $this->createBooking($passenger, $flight);
        Notification::fake();

        $this->actingAs($admin)
            ->put(route('admin.flights.update', $flight), [
                'flight_number' => $flight->flight_number,
                'airline' => $flight->airline,
                'departure_airport' => $flight->departure_airport,
                'arrival_airport' => $flight->arrival_airport,
                'departure_date' => $flight->departure_date->toDateString(),
                'departure_time' => $flight->departure_time,
                'arrival_time' => $flight->arrival_time,
                'aircraft' => $flight->aircraft,
                'total_seats' => $flight->total_seats,
                'available_seats' => $flight->available_seats,
                'status' => Flight::STATUS_BOARDING,
            ])
            ->assertRedirect();

        Notification::assertSentTo(
            $passenger,
            PassengerUpdateNotification::class,
            fn (PassengerUpdateNotification $notification): bool => $notification->event === 'flight_status_changed',
        );
    }

    public function test_passenger_can_view_and_mark_only_their_own_notifications_as_read(): void
    {
        $passenger = User::factory()->create(['role' => 'passenger']);
        $otherPassenger = User::factory()->create(['role' => 'passenger']);
        $notification = new PassengerUpdateNotification(
            'booking_confirmed',
            'Booking confirmed',
            'Your booking is confirmed.',
            route('bookings.index'),
        );
        $passenger->notifyNow($notification, ['database']);
        $otherPassenger->notifyNow($notification, ['database']);
        $passengerNotification = $passenger->notifications()->firstOrFail();

        $this->actingAs($passenger)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('Booking confirmed')
            ->assertSee('1 unread');

        $this->post(route('notifications.read', $passengerNotification->getKey()))
            ->assertRedirect(route('notifications.index'));

        $this->assertNotNull($passengerNotification->fresh()->read_at);
        $this->post(route('notifications.read', $otherPassenger->notifications()->firstOrFail()->getKey()))
            ->assertNotFound();
    }

    public function test_notification_history_is_restricted_to_passengers(): void
    {
        $this->get(route('notifications.index'))->assertRedirect(route('login'));

        $this->actingAs(User::factory()->create(['role' => 'staff']))
            ->get(route('notifications.index'))
            ->assertForbidden();
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
