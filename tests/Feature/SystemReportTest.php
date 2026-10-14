<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Flight;
use App\Models\User;
use App\Models\VerificationRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SystemReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_reports_and_csv_exports_are_only_available_to_administrators(): void
    {
        $this->get(route('admin.reports.index'))->assertRedirect(route('login'));
        $this->get(route('admin.reports.export'))->assertRedirect(route('login'));

        $this->actingAs(User::factory()->create(['role' => 'passenger']))
            ->get(route('admin.reports.index'))
            ->assertForbidden();

        $this->get(route('admin.reports.export'))->assertForbidden();
    }

    public function test_every_operational_report_renders_for_an_administrator(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        foreach ([
            'boarding-activity',
            'flights',
            'passengers',
            'bookings',
            'check-ins',
            'boarding-passes',
            'qr-verifications',
            'failed-verifications',
            'offline-verifications',
            'synchronization',
            'audit-activity',
        ] as $report) {
            $this->get(route('admin.reports.index', ['report' => $report]))
                ->assertOk()
                ->assertViewHas('selectedReport', $report);
        }
    }

    public function test_verification_report_applies_date_flight_airport_result_staff_and_method_filters(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $passenger = User::factory()->create(['role' => 'passenger']);
        $staff = User::factory()->create(['role' => 'staff']);
        $otherStaff = User::factory()->create(['role' => 'staff']);
        $flight = Flight::factory()->create([
            'departure_airport' => 'RIV',
            'arrival_airport' => 'LKV',
        ]);
        $matchingReference = 'MATCHING-REPORT';
        $excludedReference = 'EXCLUDED-REPORT';

        VerificationRecord::create([
            'passenger_id' => $passenger->getKey(),
            'flight_id' => $flight->getKey(),
            'staff_id' => $staff->getKey(),
            'verification_reference' => $matchingReference,
            'result' => VerificationRecord::RESULT_INVALID,
            'verification_method' => VerificationRecord::METHOD_OFFLINE,
            'verified_at' => now(),
        ]);
        VerificationRecord::create([
            'passenger_id' => $passenger->getKey(),
            'flight_id' => $flight->getKey(),
            'staff_id' => $otherStaff->getKey(),
            'verification_reference' => $excludedReference,
            'result' => VerificationRecord::RESULT_VALID,
            'verification_method' => VerificationRecord::METHOD_ONLINE,
            'verified_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.reports.index', [
                'report' => 'qr-verifications',
                'start_date' => now()->toDateString(),
                'end_date' => now()->toDateString(),
                'flight_id' => $flight->getKey(),
                'airport' => 'RIV',
                'verification_result' => VerificationRecord::RESULT_INVALID,
                'staff_id' => $staff->getKey(),
                'verification_method' => VerificationRecord::METHOD_OFFLINE,
            ]))
            ->assertOk()
            ->assertViewHas('summary', fn (array $summary): bool => $summary['total'] === 1)
            ->assertSee($matchingReference)
            ->assertDontSee($excludedReference);
    }

    public function test_csv_export_escapes_formula_values_and_omits_authentication_data(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $description = '=HYPERLINK("https://example.invalid","unsafe")';

        AuditLog::create([
            'actor_id' => $admin->getKey(),
            'event' => 'report.test',
            'description' => $description,
            'ip_address' => '192.0.2.10',
            'user_agent' => 'private user agent',
            'occurred_at' => now(),
        ]);

        $response = $this->actingAs($admin)
            ->get(route('admin.reports.export', ['report' => 'audit-activity']));

        $response->assertOk();
        $csv = $response->streamedContent();

        $this->assertStringContainsString("'=HYPERLINK(", $csv);
        $this->assertStringNotContainsString('192.0.2.10', $csv);
        $this->assertStringNotContainsString('private user agent', $csv);
        $this->assertStringNotContainsString($admin->getAuthPassword(), $csv);
    }

    public function test_report_rejects_an_invalid_date_range(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get(route('admin.reports.index', [
                'start_date' => '2026-10-20',
                'end_date' => '2026-10-19',
            ]))
            ->assertSessionHasErrors('end_date');
    }

    public function test_audit_report_flight_and_airport_filters_include_booking_events(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $passenger = User::factory()->create(['role' => 'passenger']);
        $flight = Flight::factory()->create([
            'departure_airport' => 'RIV',
            'arrival_airport' => 'LKV',
        ]);
        $booking = $passenger->bookings()->create([
            'booking_reference' => 'DBP-'.strtoupper((string) Str::ulid()),
            'flight_id' => $flight->getKey(),
            'booking_status' => Booking::STATUS_CONFIRMED,
            'booking_date' => now(),
            'number_of_passengers' => 1,
        ]);
        AuditLog::create([
            'actor_id' => $passenger->getKey(),
            'event' => AuditLog::EVENT_BOOKING_CREATED,
            'subject_type' => $booking->getMorphClass(),
            'subject_id' => $booking->getKey(),
            'description' => 'Passenger booking created.',
            'occurred_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.reports.index', [
                'report' => 'audit-activity',
                'flight_id' => $flight->getKey(),
                'airport' => 'RIV',
            ]))
            ->assertOk()
            ->assertSee(AuditLog::EVENT_BOOKING_CREATED)
            ->assertViewHas('summary', fn (array $summary): bool => $summary['total'] === 1);
    }
}
