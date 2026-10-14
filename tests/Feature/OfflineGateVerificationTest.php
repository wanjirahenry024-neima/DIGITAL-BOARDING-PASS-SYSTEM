<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\BoardingPass;
use App\Models\BoardingPassOfflineLease;
use App\Models\BoardingPassVerification;
use App\Models\Booking;
use App\Models\CheckIn;
use App\Models\Flight;
use App\Models\GatePassSnapshot;
use App\Models\GateTerminal;
use App\Models\GateVerificationOutbox;
use App\Models\User;
use App\Models\VerificationRecord;
use App\Services\GateOfflineVerificationService;
use App\Services\GateSyncService;
use App\Services\GateSyncSignature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('offline')]
#[Group('security')]
class OfflineGateVerificationTest extends TestCase
{
    use RefreshDatabase;

    private string $gateDatabasePath;

    /**
     * @var array{process: string|false, environment: mixed, server: mixed}
     */
    private array $originalGateSqliteEnvironment;

    protected function setUp(): void
    {
        $this->originalGateSqliteEnvironment = [
            'process' => getenv('GATE_SQLITE_PATH'),
            'environment' => $_ENV['GATE_SQLITE_PATH'] ?? null,
            'server' => $_SERVER['GATE_SQLITE_PATH'] ?? null,
        ];
        $this->gateDatabasePath = tempnam(sys_get_temp_dir(), 'gate-terminal-test-');
        putenv('GATE_SQLITE_PATH='.$this->gateDatabasePath);
        $_ENV['GATE_SQLITE_PATH'] = $this->gateDatabasePath;
        $_SERVER['GATE_SQLITE_PATH'] = $this->gateDatabasePath;

        parent::setUp();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        if (file_exists($this->gateDatabasePath)) {
            unlink($this->gateDatabasePath);
        }

        if ($this->originalGateSqliteEnvironment['process'] === false) {
            putenv('GATE_SQLITE_PATH');
        } else {
            putenv('GATE_SQLITE_PATH='.$this->originalGateSqliteEnvironment['process']);
        }

        foreach (['environment' => '_ENV', 'server' => '_SERVER'] as $valueKey => $arrayName) {
            if ($this->originalGateSqliteEnvironment[$valueKey] === null) {
                unset($GLOBALS[$arrayName]['GATE_SQLITE_PATH']);
            } else {
                $GLOBALS[$arrayName]['GATE_SQLITE_PATH'] = $this->originalGateSqliteEnvironment[$valueKey];
            }
        }
    }

    public function test_central_staff_scan_is_recorded_as_online(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $boardingPass = $this->issuePass();

        $response = $this->actingAs($staff)
            ->postJson(route('staff.boarding-passes.verify'), [
                'payload' => $this->payloadFor($boardingPass),
            ])
            ->assertOk()
            ->assertJsonPath('status', BoardingPassVerification::STATUS_VALID);

        $this->assertDatabaseHas('verification_records', [
            'id' => $response->json('verification_record_id'),
            'boarding_pass_id' => $boardingPass->getKey(),
            'staff_id' => $staff->getKey(),
            'verification_method' => VerificationRecord::METHOD_ONLINE,
            'result' => VerificationRecord::RESULT_VALID,
        ]);
    }

    public function test_local_terminal_verifies_offline_and_syncs_each_scan_idempotently(): void
    {
        $this->initializeLocalDatabase();

        $staff = User::factory()->create(['role' => 'staff']);
        $staff->update(['central_user_id' => $staff->getKey()]);
        $boardingPass = $this->issuePass();
        $terminal = $this->createTerminal();
        $this->enableLocalTerminal($terminal);
        $this->downloadLocalSnapshot($terminal);

        $offlineService = app(GateOfflineVerificationService::class);
        $valid = $offlineService->scan($this->payloadFor($boardingPass), $staff);
        $invalid = $offlineService->scan(
            BoardingPass::QR_FORMAT_VERSION.'|'.$boardingPass->pass_reference.'|'.str_repeat('0', 64),
            $staff,
        );
        $offlineService->approve($valid['verification_id'], $staff);
        $used = $offlineService->scan($this->payloadFor($boardingPass), $staff);

        $this->assertSame(BoardingPassVerification::STATUS_VALID, $valid['status']);
        $this->assertSame($boardingPass->passenger->name, $valid['passenger']);
        $this->assertSame(BoardingPassVerification::STATUS_INVALID, $invalid['status']);
        $this->assertSame(BoardingPassVerification::STATUS_USED, $used['status']);
        $snapshot = GatePassSnapshot::query()->firstOrFail();
        $this->assertSame($boardingPass->passenger->name, $snapshot->passenger_name);
        $this->assertSame(
            hash('sha256', $boardingPass->verification_token),
            $snapshot->verification_token_hash,
        );
        $this->assertArrayNotHasKey('email', $snapshot->getAttributes());
        $this->assertDatabaseHas('boarding_passes', [
            'id' => $boardingPass->getKey(),
            'pass_status' => BoardingPass::STATUS_ACTIVE,
        ]);
        $this->assertDatabaseCount('verification_records', 0);

        $outbox = GateVerificationOutbox::query()->orderBy('id')->get();
        $this->assertCount(3, $outbox);
        $this->assertSame(
            [VerificationRecord::METHOD_OFFLINE],
            $outbox->pluck('verification_method')->unique()->values()->all(),
        );
        $this->assertArrayNotHasKey('verification_token', $outbox->first()->getAttributes());

        $events = $outbox->map(fn (GateVerificationOutbox $record): array => $this->eventPayload($record, $terminal))->all();
        $response = $this->withToken($terminal->api_token)
            ->postJson(route('api.gate.synchronize'), ['events' => $events])
            ->assertOk();

        $this->assertSame(
            [VerificationRecord::SYNC_SYNCED, VerificationRecord::SYNC_SYNCED, VerificationRecord::SYNC_SYNCED],
            collect($response->json('results'))->pluck('status')->all(),
        );
        $this->assertDatabaseHas('boarding_passes', [
            'id' => $boardingPass->getKey(),
            'pass_status' => BoardingPass::STATUS_USED,
        ]);
        $this->assertDatabaseCount('verification_records', 3);
        $this->assertDatabaseHas('verification_records', [
            'sync_id' => $valid['verification_id'],
            'result' => VerificationRecord::RESULT_VALID,
            'verification_method' => VerificationRecord::METHOD_OFFLINE,
            'sync_status' => VerificationRecord::SYNC_SYNCED,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'event' => AuditLog::EVENT_BOARDING_APPROVED,
            'actor_id' => $staff->getKey(),
            'subject_id' => $boardingPass->getKey(),
        ]);

        $this->withToken($terminal->api_token)
            ->postJson(route('api.gate.synchronize'), ['events' => $events])
            ->assertOk();

        $this->assertDatabaseCount('verification_records', 3);
    }

    public function test_offline_approval_from_centrally_deactivated_staff_is_rejected_during_sync(): void
    {
        $this->initializeLocalDatabase();

        $staff = User::factory()->create(['role' => 'staff']);
        $staff->update(['central_user_id' => $staff->getKey()]);
        $boardingPass = $this->issuePass();
        $terminal = $this->createTerminal();
        $this->enableLocalTerminal($terminal);
        $this->downloadLocalSnapshot($terminal);

        $offlineService = app(GateOfflineVerificationService::class);
        $valid = $offlineService->scan($this->payloadFor($boardingPass), $staff);
        $offlineService->approve($valid['verification_id'], $staff);
        $staff->update(['status' => 'inactive']);

        $outbox = GateVerificationOutbox::query()->firstOrFail();
        $event = $this->eventPayload($outbox, $terminal);
        $response = $this->withToken($terminal->api_token)
            ->postJson(route('api.gate.synchronize'), ['events' => [$event]])
            ->assertOk();

        $this->assertSame(
            VerificationRecord::SYNC_CONFLICT,
            $response->json('results.0.status'),
        );
        $this->assertSame(
            'The synchronized staff identity is inactive or not valid.',
            $response->json('results.0.reason'),
        );
        $this->assertDatabaseHas('boarding_passes', [
            'id' => $boardingPass->getKey(),
            'pass_status' => BoardingPass::STATUS_ACTIVE,
        ]);
        $this->assertDatabaseCount('verification_records', 0);
        $this->assertDatabaseMissing('audit_logs', [
            'event' => AuditLog::EVENT_BOARDING_APPROVED,
            'subject_id' => $boardingPass->getKey(),
        ]);
    }

    public function test_offline_reservation_excludes_other_terminals_and_blocks_central_approval(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $boardingPass = $this->issuePass();
        $firstTerminal = $this->createTerminal('Gate Terminal One');
        $secondTerminal = $this->createTerminal('Gate Terminal Two');

        $firstSnapshot = $this->withToken($firstTerminal->api_token)
            ->getJson(route('api.gate.snapshot'))
            ->assertOk()
            ->assertJsonCount(1, 'snapshots');
        $this->withToken($secondTerminal->api_token)
            ->getJson(route('api.gate.snapshot'))
            ->assertOk()
            ->assertJsonCount(0, 'snapshots');

        $this->actingAs($staff)
            ->postJson(route('staff.boarding-passes.verify'), [
                'payload' => $this->payloadFor($boardingPass),
            ])
            ->assertOk()
            ->assertJsonPath('status', BoardingPassVerification::STATUS_CONFLICT)
            ->assertJsonPath('can_approve', false);

        $this->assertDatabaseHas('boarding_pass_offline_leases', [
            'boarding_pass_id' => $boardingPass->getKey(),
            'gate_terminal_id' => $firstTerminal->getKey(),
            'lease_id' => $firstSnapshot->json('snapshots.0.lease_id'),
            'status' => BoardingPassOfflineLease::STATUS_ACTIVE,
        ]);
    }

    public function test_terminal_downloads_all_snapshot_pages_and_revokes_cancelled_passes(): void
    {
        $this->initializeLocalDatabase();

        $terminal = $this->createTerminal();
        $this->enableLocalTerminal($terminal);
        $firstPass = $this->issuePass();
        $secondPass = $this->issuePass();
        config(['services.gate_terminal.snapshot_batch_size' => 1]);
        $pages = $this->snapshotPages($terminal);

        Http::preventStrayRequests();
        Http::fake([
            'https://central.example/api/gate/v1/snapshot*' => function (Request $request) use (&$pages) {
                parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

                return Http::response($pages[(int) ($query['after_id'] ?? 0)]);
            },
        ]);

        $initialSync = app(GateSyncService::class)->synchronize();

        $this->assertTrue($initialSync['connected']);
        $this->assertSame(2, $initialSync['snapshots_downloaded']);
        $this->assertDatabaseCount('gate_pass_snapshots', 2, 'gate_terminal');

        $firstPass->booking->update(['booking_status' => Booking::STATUS_CANCELLED]);
        $secondPass->flight->update(['status' => Flight::STATUS_CANCELLED]);
        $pages = $this->snapshotPages($terminal);

        $updatedSync = app(GateSyncService::class)->synchronize();

        $this->assertTrue($updatedSync['connected']);
        $this->assertSame(0, $updatedSync['snapshots_downloaded']);
        $this->assertDatabaseHas('gate_pass_snapshots', [
            'pass_reference' => $firstPass->pass_reference,
            'is_enabled' => false,
        ], 'gate_terminal');
        $this->assertDatabaseHas('gate_pass_snapshots', [
            'pass_reference' => $secondPass->pass_reference,
            'is_enabled' => false,
        ], 'gate_terminal');
    }

    public function test_offline_terminal_rejects_a_pass_after_its_flight_departure(): void
    {
        $this->initializeLocalDatabase();

        $staff = User::factory()->create(['role' => 'staff']);
        $staff->update(['central_user_id' => $staff->getKey()]);
        $boardingPass = $this->issuePass();
        $departure = now()->addHours(23);
        $boardingPass->flight->update([
            'departure_date' => $departure->toDateString(),
            'departure_time' => $departure->format('H:i:s'),
        ]);
        $terminal = $this->createTerminal();
        $this->enableLocalTerminal($terminal);
        config(['services.gate_terminal.offline_window_minutes' => 1440]);
        $this->downloadLocalSnapshot($terminal);

        $this->travelTo($departure->copy()->addMinute());
        $result = app(GateOfflineVerificationService::class)->scan(
            $this->payloadFor($boardingPass),
            $staff,
        );

        $this->assertSame(BoardingPassVerification::STATUS_EXPIRED, $result['status']);
        $this->assertFalse($result['can_approve']);
    }

    public function test_synchronized_valid_scan_without_its_terminal_lease_becomes_a_conflict(): void
    {
        $this->initializeLocalDatabase();

        $staff = User::factory()->create(['role' => 'staff']);
        $staff->update(['central_user_id' => $staff->getKey()]);
        $boardingPass = $this->issuePass();
        $terminal = $this->createTerminal();
        $this->enableLocalTerminal($terminal);
        $this->downloadLocalSnapshot($terminal);

        app(GateOfflineVerificationService::class)->scan($this->payloadFor($boardingPass), $staff);
        $outbox = GateVerificationOutbox::query()->firstOrFail();
        $event = $this->eventPayload($outbox, $terminal);
        $event['lease_id'] = (string) Str::uuid();
        $event['event_signature'] = app(GateSyncSignature::class)->signEvent($event, $terminal->api_token);

        $response = $this->withToken($terminal->api_token)
            ->postJson(route('api.gate.synchronize'), ['events' => [$event]])
            ->assertOk();

        $this->assertSame(
            VerificationRecord::SYNC_CONFLICT,
            $response->json('results.0.status'),
        );
        $this->assertDatabaseHas('verification_records', [
            'sync_id' => $outbox->idempotency_key,
            'result' => VerificationRecord::RESULT_VALID,
            'sync_status' => VerificationRecord::SYNC_CONFLICT,
        ]);
        $this->assertDatabaseHas('boarding_passes', [
            'id' => $boardingPass->getKey(),
            'pass_status' => BoardingPass::STATUS_ACTIVE,
        ]);
        $this->assertDatabaseMissing('audit_logs', [
            'event' => AuditLog::EVENT_BOARDING_APPROVED,
            'subject_id' => $boardingPass->getKey(),
        ]);
    }

    public function test_failed_sync_attempt_is_queued_with_retry_and_later_succeeds(): void
    {
        $this->initializeLocalDatabase();

        $staff = User::factory()->create(['role' => 'staff']);
        $staff->update(['central_user_id' => $staff->getKey()]);
        $terminal = $this->createTerminal();
        $this->enableLocalTerminal($terminal);
        app(GateOfflineVerificationService::class)->scan('malformed-qr', $staff);

        Http::preventStrayRequests();
        $networkAvailable = false;
        $idempotencyKey = null;
        Http::fake([
            'https://central.example/api/gate/v1/snapshot*' => function (Request $request) use (&$networkAvailable, $terminal) {
                if (! $networkAvailable) {
                    return Http::failedConnection();
                }

                return Http::response([
                    'terminal_id' => $terminal->terminal_id,
                    'revoked_lease_ids' => [],
                    'revocations_signature' => app(GateSyncSignature::class)->signRevocations(
                        [],
                        $terminal->api_token,
                    ),
                    'snapshots' => [],
                ]);
            },
            'https://central.example/api/gate/v1/synchronize' => function () use (&$networkAvailable, &$idempotencyKey) {
                if (! $networkAvailable) {
                    return Http::failedConnection();
                }

                return Http::response([
                    'results' => [[
                        'idempotency_key' => $idempotencyKey,
                        'status' => VerificationRecord::SYNC_SYNCED,
                        'verification_record_id' => 991,
                    ]],
                ]);
            },
        ]);

        $firstAttempt = app(GateSyncService::class)->synchronize();

        $this->assertFalse($firstAttempt['connected']);
        $this->assertDatabaseHas('gate_verification_outbox', [
            'result' => BoardingPassVerification::STATUS_INVALID,
            'sync_status' => GateVerificationOutbox::SYNC_FAILED,
        ], 'gate_terminal');
        $this->assertNotNull(GateVerificationOutbox::query()->firstOrFail()->next_attempt_at);

        $this->travel(2)->minutes();
        $record = GateVerificationOutbox::query()->firstOrFail();
        $idempotencyKey = $record->idempotency_key;
        $networkAvailable = true;

        $retry = app(GateSyncService::class)->synchronize();

        $this->assertTrue($retry['connected'], json_encode($retry, JSON_THROW_ON_ERROR));
        $this->assertSame(1, $retry['events_synchronized']);
        $this->assertDatabaseHas('gate_verification_outbox', [
            'idempotency_key' => $record->idempotency_key,
            'sync_status' => GateVerificationOutbox::SYNC_SYNCED,
            'server_verification_record_id' => 991,
        ], 'gate_terminal');
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://central.example/api/gate/v1/synchronize'
            && $request['events'][0]['idempotency_key'] === $record->idempotency_key
        );
    }

    private function issuePass(): BoardingPass
    {
        $passenger = User::factory()->create(['role' => 'passenger']);
        $flight = Flight::factory()->create([
            'departure_date' => now()->addDay()->toDateString(),
            'departure_time' => '23:00',
        ]);
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
            'seat_number' => '001',
            'status' => CheckIn::STATUS_CHECKED_IN,
        ]);
        $token = bin2hex(random_bytes(32));

        return BoardingPass::create([
            'pass_reference' => 'BP-'.strtoupper((string) Str::ulid()),
            'booking_id' => $booking->getKey(),
            'passenger_id' => $passenger->getKey(),
            'flight_id' => $flight->getKey(),
            'check_in_id' => $checkIn->getKey(),
            'seat_number' => '001',
            'pass_status' => BoardingPass::STATUS_ACTIVE,
            'issued_at' => now(),
            'expires_at' => now()->addDay()->setTime(22, 0),
            'verification_token' => $token,
            'verification_token_hash' => hash('sha256', $token),
        ]);
    }

    private function createTerminal(string $name = 'Gate Terminal'): GateTerminal
    {
        $token = bin2hex(random_bytes(32));

        return GateTerminal::create([
            'terminal_id' => (string) Str::uuid(),
            'name' => $name,
            'gate' => 'A4',
            'api_token_hash' => hash('sha256', $token),
            'api_token' => $token,
            'is_active' => true,
        ]);
    }

    private function enableLocalTerminal(GateTerminal $terminal): void
    {
        config([
            'services.gate_terminal.enabled' => true,
            'services.gate_terminal.id' => $terminal->terminal_id,
            'services.gate_terminal.token' => $terminal->api_token,
            'services.gate_terminal.central_url' => 'https://central.example',
            'services.gate_terminal.sync_batch_size' => 100,
        ]);
    }

    private function downloadLocalSnapshot(GateTerminal $terminal): void
    {
        $centralResponse = $this->withToken($terminal->api_token)
            ->getJson(route('api.gate.snapshot'))
            ->assertOk();

        Http::preventStrayRequests();
        Http::fake([
            'https://central.example/api/gate/v1/snapshot*' => Http::response($centralResponse->json()),
        ]);

        $result = app(GateSyncService::class)->synchronize();
        $this->assertTrue($result['connected']);
        $this->assertSame(1, $result['snapshots_downloaded']);
    }

    private function initializeLocalDatabase(): void
    {
        $defaultConnection = DB::getDefaultConnection();
        DB::setDefaultConnection('gate_terminal');

        try {
            foreach ([
                '2026_10_14_053128_create_gate_pass_snapshots_table.php' => 'gate_pass_snapshots',
                '2026_10_14_053129_create_gate_verification_outbox_table.php' => 'gate_verification_outbox',
                '2026_10_14_053130_create_gate_sync_state_table.php' => 'gate_sync_state',
            ] as $migrationFile => $tableName) {
                if (! Schema::connection('gate_terminal')->hasTable($tableName)) {
                    $migration = require database_path('migrations/'.$migrationFile);
                    $migration->up();
                }
            }

            if (! Schema::connection('gate_terminal')->hasColumn('gate_pass_snapshots', 'passenger_name')) {
                $migration = require database_path('migrations/2026_10_14_060745_add_passenger_name_to_gate_pass_snapshots_table.php');
                $migration->up();
            }
        } finally {
            DB::setDefaultConnection($defaultConnection);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function snapshotPages(GateTerminal $terminal): array
    {
        $pages = [];
        $afterId = 0;

        do {
            $response = $this->withToken($terminal->api_token)
                ->getJson(route('api.gate.snapshot', ['after_id' => $afterId, 'per_page' => 1]))
                ->assertOk();
            $pages[$afterId] = $response->json();
            $nextCursor = $response->json('next_cursor');

            if ($nextCursor === null) {
                break;
            }

            $afterId = (int) $nextCursor;
        } while (true);

        return $pages;
    }

    /**
     * @return array<string, mixed>
     */
    private function eventPayload(GateVerificationOutbox $outbox, GateTerminal $terminal): array
    {
        $event = [
            'idempotency_key' => $outbox->idempotency_key,
            'boarding_pass_id' => $outbox->boarding_pass_id,
            'flight_id' => $outbox->flight_id,
            'passenger_id' => $outbox->passenger_id,
            'staff_id' => $outbox->staff_id,
            'lease_id' => $outbox->lease_id,
            'verification_reference' => $outbox->verification_reference,
            'result' => $outbox->result,
            'verification_method' => $outbox->verification_method,
            'gate' => $outbox->gate,
            'device_identifier' => $outbox->device_identifier,
            'verified_at' => $outbox->verified_at->toIso8601String(),
            'notes' => $outbox->notes,
            'approved_at' => $outbox->approved_at?->toIso8601String(),
        ];
        $event['event_signature'] = app(GateSyncSignature::class)->signEvent($event, $terminal->api_token);

        return $event;
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
