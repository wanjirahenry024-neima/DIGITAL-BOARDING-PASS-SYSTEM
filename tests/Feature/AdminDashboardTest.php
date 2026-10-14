<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\BoardingPass;
use App\Models\Booking;
use App\Models\CheckIn;
use App\Models\Flight;
use App\Models\User;
use App\Models\VerificationRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('security')]
class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_shows_operational_totals_and_recent_gate_activity(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $passenger = User::factory()->create(['role' => 'passenger']);
        $staff = User::factory()->create(['role' => 'staff']);
        $flight = Flight::factory()->create();
        $activePass = $this->createBoardingPass($passenger, $flight, BoardingPass::STATUS_ACTIVE);
        $usedPass = $this->createBoardingPass($passenger, $flight, BoardingPass::STATUS_USED, '002', now());
        VerificationRecord::create([
            'boarding_pass_id' => $activePass->getKey(),
            'passenger_id' => $passenger->getKey(),
            'flight_id' => $flight->getKey(),
            'staff_id' => $staff->getKey(),
            'verification_reference' => $activePass->pass_reference,
            'result' => VerificationRecord::RESULT_INVALID,
            'verification_method' => VerificationRecord::METHOD_ONLINE,
            'verified_at' => now(),
        ]);
        VerificationRecord::create([
            'boarding_pass_id' => $usedPass->getKey(),
            'passenger_id' => $passenger->getKey(),
            'flight_id' => $flight->getKey(),
            'staff_id' => $staff->getKey(),
            'verification_reference' => $usedPass->pass_reference,
            'result' => VerificationRecord::RESULT_VALID,
            'verification_method' => VerificationRecord::METHOD_OFFLINE,
            'verified_at' => now(),
            'sync_status' => VerificationRecord::SYNC_FAILED,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('statistics', function (array $statistics): bool {
                return $statistics['passengers'] === 1
                    && $statistics['staff'] === 1
                    && $statistics['flights'] === 1
                    && $statistics['bookings'] === 2
                    && $statistics['check_ins'] === 2
                    && $statistics['active_boarding_passes'] === 1
                    && $statistics['used_boarding_passes'] === 1
                    && $statistics['invalid_verifications'] === 1
                    && $statistics['today_boarding_activity'] === 1
                    && $statistics['offline_verifications'] === 1
                    && $statistics['pending_synchronization'] === 1;
            })
            ->assertSee('Operations dashboard')
            ->assertSee('Recent verifications');
    }

    public function test_only_administrators_can_access_admin_dashboard_and_management_pages(): void
    {
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));

        foreach (['passenger', 'staff'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get(route('admin.dashboard'))
                ->assertForbidden();
        }

        $this->actingAs(User::factory()->create(['role' => 'admin']));

        foreach ([
            'admin.users.index',
            'admin.flights.index',
            'admin.bookings.index',
            'admin.boarding-passes.index',
            'admin.verification-records.index',
            'admin.audit-logs.index',
            'admin.reports.index',
            'admin.profile',
        ] as $route) {
            $this->get(route($route))->assertOk();
        }
    }

    public function test_user_management_searches_safely_and_does_not_expose_passwords(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $managedUser = User::factory()->create([
            'full_name' => 'Airport Agent',
            'role' => 'passenger',
            'password' => 'a-known-test-password',
        ]);
        User::factory()->create(['full_name' => 'Different Passenger']);

        $this->actingAs($admin)
            ->get(route('admin.users.index', ['q' => 'Airport Agent', 'role' => 'passenger']))
            ->assertOk()
            ->assertSee('Airport Agent')
            ->assertDontSee('Different Passenger')
            ->assertDontSee('a-known-test-password')
            ->assertDontSee($managedUser->getAuthPassword());

        $this->patch(route('admin.users.update', $managedUser), [
            'role' => 'staff',
            'status' => 'active',
        ])->assertRedirect()
            ->assertSessionHas('status', 'User account updated.');

        $this->assertDatabaseHas('users', [
            'id' => $managedUser->getKey(),
            'role' => 'staff',
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'event' => AuditLog::EVENT_ADMINISTRATOR_CHANGE,
            'actor_id' => $admin->getKey(),
            'subject_id' => $managedUser->getKey(),
        ]);
    }

    public function test_administrator_cannot_remove_their_own_access(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $otherAdmin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->patch(route('admin.users.update', $admin), [
                'role' => 'staff',
                'status' => 'active',
            ])
            ->assertSessionHasErrors('user');

        $this->patch(route('admin.users.update', $otherAdmin), [
            'role' => 'staff',
            'status' => 'active',
        ])->assertRedirect();

        $this->assertDatabaseHas('users', [
            'id' => $admin->getKey(),
            'role' => 'admin',
            'status' => 'active',
        ]);
    }

    public function test_administrator_lists_filterable_passes_and_verification_records(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $passenger = User::factory()->create(['role' => 'passenger']);
        $staff = User::factory()->create(['role' => 'staff']);
        $flight = Flight::factory()->create();
        $pass = $this->createBoardingPass($passenger, $flight, BoardingPass::STATUS_ACTIVE);
        VerificationRecord::create([
            'boarding_pass_id' => $pass->getKey(),
            'passenger_id' => $passenger->getKey(),
            'flight_id' => $flight->getKey(),
            'staff_id' => $staff->getKey(),
            'verification_reference' => $pass->pass_reference,
            'result' => VerificationRecord::RESULT_INVALID,
            'verification_method' => VerificationRecord::METHOD_OFFLINE,
            'verified_at' => now(),
            'sync_status' => VerificationRecord::SYNC_CONFLICT,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.boarding-passes.index', ['q' => $pass->pass_reference, 'pass_status' => 'ACTIVE']))
            ->assertOk()
            ->assertSee($pass->pass_reference)
            ->assertDontSee('DBP-UNRELATED');

        $this->get(route('admin.verification-records.index', [
            'result' => VerificationRecord::RESULT_INVALID,
            'verification_method' => VerificationRecord::METHOD_OFFLINE,
            'sync_status' => VerificationRecord::SYNC_CONFLICT,
        ]))
            ->assertOk()
            ->assertSee($pass->pass_reference)
            ->assertSee('CONFLICT');
    }

    private function createBoardingPass(
        User $passenger,
        Flight $flight,
        string $status,
        string $seatNumber = '001',
        mixed $usedAt = null,
    ): BoardingPass {
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
            'seat_number' => $seatNumber,
            'status' => CheckIn::STATUS_CHECKED_IN,
        ]);

        return BoardingPass::create([
            'pass_reference' => 'BP-'.strtoupper((string) Str::ulid()),
            'booking_id' => $booking->getKey(),
            'passenger_id' => $passenger->getKey(),
            'flight_id' => $flight->getKey(),
            'check_in_id' => $checkIn->getKey(),
            'seat_number' => $seatNumber,
            'pass_status' => $status,
            'issued_at' => now(),
            'expires_at' => now()->addDay(),
            'used_at' => $usedAt,
        ]);
    }
}
