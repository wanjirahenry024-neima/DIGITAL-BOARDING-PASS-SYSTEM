<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('security')]
class FoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_homepage_is_available(): void
    {
        $this->get('/')->assertOk()->assertSee(config('app.name'));
    }

    public function test_authentication_forms_render(): void
    {
        $this->get('/login')->assertOk()->assertSee('Welcome back');
        $this->get('/register')->assertOk()->assertSee('Phone number');
        $this->get('/forgot-password')->assertOk()->assertSee('Reset your password');
        $this->get('/reset-password/reset-token?email=passenger%40example.com')
            ->assertOk()
            ->assertSee('Choose a new password');
    }

    public function test_passenger_can_register_and_reach_dashboard(): void
    {
        $this->post('/register', [
            'full_name' => 'Avery Passenger',
            'email' => 'avery@example.com',
            'phone' => '+15551234567',
            'role' => 'admin',
            'password' => 'secure-pass-123',
            'password_confirmation' => 'secure-pass-123',
        ])->assertRedirect('/dashboard');

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'email' => 'avery@example.com',
            'phone' => '+15551234567',
            'role' => 'passenger',
            'status' => 'active',
        ]);
        $this->assertTrue(Hash::check('secure-pass-123', User::whereEmail('avery@example.com')->firstOrFail()->password));
        $this->get('/dashboard')->assertOk()->assertSee('Avery Passenger');
    }

    public function test_admin_area_rejects_passengers(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'passenger']))
            ->get('/admin')
            ->assertForbidden();
    }

    public function test_admin_can_open_admin_dashboard(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get('/admin')
            ->assertOk()
            ->assertSee('Operations dashboard');
    }

    public function test_staff_can_only_open_staff_workspace(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'staff']))
            ->get('/staff')
            ->assertOk()
            ->assertSee('Staff workspace');

        $this->get('/dashboard')->assertForbidden();
        $this->get('/admin')->assertForbidden();
    }

    public function test_passenger_cannot_open_staff_or_admin_urls(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'passenger']))
            ->get('/staff')
            ->assertForbidden();

        $this->get('/admin')->assertForbidden();
    }

    public function test_admin_cannot_open_passenger_or_staff_urls(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get('/dashboard')
            ->assertForbidden();

        $this->get('/staff')->assertForbidden();
    }

    public function test_login_redirects_each_role_to_its_own_area(): void
    {
        foreach ([
            ['role' => 'passenger', 'url' => '/dashboard'],
            ['role' => 'staff', 'url' => '/staff'],
            ['role' => 'admin', 'url' => '/admin'],
        ] as $account) {
            $user = User::factory()->create(['role' => $account['role'], 'password' => 'password']);

            $this->post('/login', [
                'email' => $user->email,
                'password' => 'password',
            ])->assertRedirect($account['url']);

            auth()->logout();
        }
    }

    public function test_login_role_destination_overrides_untrusted_intended_url(): void
    {
        $user = User::factory()->create(['role' => 'staff', 'password' => 'password']);

        $this->withSession(['url.intended' => '/dashboard'])
            ->post('/login', [
                'email' => $user->email,
                'password' => 'password',
            ])
            ->assertRedirect('/staff');
    }

    public function test_inactive_user_cannot_log_in(): void
    {
        $user = User::factory()->create(['status' => 'inactive', 'password' => 'password']);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_registration_rejects_duplicate_emails(): void
    {
        User::factory()->create(['email' => 'already@example.com']);

        $this->from('/register')->post('/register', [
            'full_name' => 'Duplicate Passenger',
            'email' => 'already@example.com',
            'phone' => '+15551234567',
            'password' => 'secure-pass-123',
            'password_confirmation' => 'secure-pass-123',
        ])->assertRedirect('/register')->assertSessionHasErrors('email');
    }

    public function test_authenticated_user_can_log_out(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/logout')
            ->assertRedirect('/');

        $this->assertGuest();
    }

    public function test_forgot_password_sends_reset_notification_and_token_resets_password(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email])
            ->assertSessionHas('status');
        Notification::assertSentTo($user, ResetPassword::class);

        $token = Password::broker()->createToken($user);

        $this->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'new-secure-pass-456',
            'password_confirmation' => 'new-secure-pass-456',
        ])->assertRedirect('/login');

        $this->assertTrue(Hash::check('new-secure-pass-456', $user->fresh()->password));
    }
}
