<?php

namespace App\Services;

class GateSyncSignature
{
    /**
     * @param  array<int, string>  $leaseIds
     */
    public function signRevocations(array $leaseIds, string $secret): string
    {
        sort($leaseIds);

        return hash_hmac('sha256', $this->json(['lease_ids' => $leaseIds]), $secret);
    }

    /**
     * @param  array<int, string>  $leaseIds
     */
    public function revocationsAreValid(array $leaseIds, string $signature, string $secret): bool
    {
        return hash_equals($this->signRevocations($leaseIds, $secret), $signature);
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    public function signSnapshot(array $snapshot, string $secret): string
    {
        return hash_hmac('sha256', $this->json($this->snapshotFields($snapshot)), $secret);
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    public function snapshotIsValid(array $snapshot, string $signature, string $secret): bool
    {
        return hash_equals($this->signSnapshot($snapshot, $secret), $signature);
    }

    /**
     * @param  array<string, mixed>  $event
     */
    public function signEvent(array $event, string $secret): string
    {
        return hash_hmac('sha256', $this->json($this->eventFields($event)), $secret);
    }

    /**
     * @param  array<string, mixed>  $event
     */
    public function eventIsValid(array $event, string $signature, string $secret): bool
    {
        return hash_equals($this->signEvent($event, $secret), $signature);
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array<string, int|string|bool>
     */
    private function snapshotFields(array $snapshot): array
    {
        return [
            'boarding_pass_id' => (int) $snapshot['boarding_pass_id'],
            'pass_reference' => (string) $snapshot['pass_reference'],
            'passenger_name' => (string) $snapshot['passenger_name'],
            'verification_token_hash' => (string) $snapshot['verification_token_hash'],
            'pass_status' => (string) $snapshot['pass_status'],
            'booking_status' => (string) $snapshot['booking_status'],
            'flight_id' => (int) $snapshot['flight_id'],
            'flight_status' => (string) $snapshot['flight_status'],
            'flight_deleted' => (bool) $snapshot['flight_deleted'],
            'flight_number' => (string) $snapshot['flight_number'],
            'destination' => (string) $snapshot['destination'],
            'seat_number' => (string) $snapshot['seat_number'],
            'departure_at' => (string) $snapshot['departure_at'],
            'expires_at' => (string) $snapshot['expires_at'],
            'lease_id' => (string) $snapshot['lease_id'],
            'offline_valid_until' => (string) $snapshot['offline_valid_until'],
        ];
    }

    /**
     * @param  array<string, mixed>  $event
     * @return array<string, int|string|null>
     */
    private function eventFields(array $event): array
    {
        return [
            'idempotency_key' => (string) $event['idempotency_key'],
            'boarding_pass_id' => isset($event['boarding_pass_id']) ? (int) $event['boarding_pass_id'] : null,
            'flight_id' => isset($event['flight_id']) ? (int) $event['flight_id'] : null,
            'passenger_id' => isset($event['passenger_id']) ? (int) $event['passenger_id'] : null,
            'staff_id' => isset($event['staff_id']) ? (int) $event['staff_id'] : null,
            'lease_id' => $event['lease_id'] ?? null,
            'verification_reference' => $event['verification_reference'] ?? null,
            'result' => (string) $event['result'],
            'verification_method' => (string) $event['verification_method'],
            'gate' => $event['gate'] ?? null,
            'device_identifier' => $event['device_identifier'] ?? null,
            'verified_at' => (string) $event['verified_at'],
            'notes' => $event['notes'] ?? null,
            'approved_at' => $event['approved_at'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    private function json(array $fields): string
    {
        return json_encode($fields, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}
