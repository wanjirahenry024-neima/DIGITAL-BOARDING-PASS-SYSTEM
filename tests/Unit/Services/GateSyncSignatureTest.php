<?php

namespace Tests\Unit\Services;

use App\Services\GateSyncSignature;
use PHPUnit\Framework\TestCase;

class GateSyncSignatureTest extends TestCase
{
    public function test_event_signature_rejects_modified_sync_data(): void
    {
        $signatureService = new GateSyncSignature;
        $secret = 'test-terminal-secret';
        $event = [
            'idempotency_key' => 'sync-1',
            'boarding_pass_id' => 17,
            'flight_id' => 23,
            'passenger_id' => 31,
            'staff_id' => 41,
            'lease_id' => 'lease-1',
            'verification_reference' => 'BP-1',
            'result' => 'VALID',
            'verification_method' => 'OFFLINE',
            'gate' => 'A2',
            'device_identifier' => 'GATE-01',
            'verified_at' => '2026-10-14T10:00:00+00:00',
            'notes' => null,
            'approved_at' => null,
        ];
        $signature = $signatureService->signEvent($event, $secret);

        $this->assertTrue($signatureService->eventIsValid($event, $signature, $secret));

        $event['result'] = 'USED';

        $this->assertFalse($signatureService->eventIsValid($event, $signature, $secret));
    }

    public function test_revocation_signature_is_order_independent_and_secret_bound(): void
    {
        $signatureService = new GateSyncSignature;
        $signature = $signatureService->signRevocations(['lease-b', 'lease-a'], 'secret-a');

        $this->assertTrue($signatureService->revocationsAreValid(['lease-a', 'lease-b'], $signature, 'secret-a'));
        $this->assertFalse($signatureService->revocationsAreValid(['lease-a', 'lease-b'], $signature, 'secret-b'));
    }
}
