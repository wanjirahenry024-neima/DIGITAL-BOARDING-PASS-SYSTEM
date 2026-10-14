<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('security')]
class SecurityRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_user_search_treats_sql_injection_payload_as_data(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        User::factory()->create([
            'full_name' => 'Unrelated Passenger',
            'email' => 'unrelated@example.com',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.users.index', ['q' => "' OR 1=1 --"]))
            ->assertOk()
            ->assertDontSee('Unrelated Passenger')
            ->assertSee('No users match these filters.');
    }

    public function test_admin_user_listing_escapes_stored_script_content(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        User::factory()->create([
            'full_name' => '<script>alert("xss")</script>',
            'email' => 'xss-passenger@example.com',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;', escape: false)
            ->assertDontSee('<script>alert("xss")</script>', escape: false);
    }

    public function test_state_changing_browser_forms_render_csrf_tokens_and_use_web_middleware(): void
    {
        $passenger = User::factory()->create(['role' => 'passenger']);

        $this->get(route('register'))
            ->assertOk()
            ->assertSee('name="_token"', escape: false);

        $this->actingAs($passenger)
            ->get(route('dashboard'))
            ->assertOk();

        $this->assertContains(
            'web',
            app('router')->getRoutes()->getByName('bookings.store')->gatherMiddleware(),
        );
        $this->assertContains(
            'web',
            app('router')->getRoutes()->getByName('check-ins.store')->gatherMiddleware(),
        );
        $this->assertContains(
            'web',
            app('router')->getRoutes()->getByName('admin.users.update')->gatherMiddleware(),
        );
    }
}
