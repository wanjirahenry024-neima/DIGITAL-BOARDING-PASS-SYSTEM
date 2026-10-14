<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'boarding_pass_id',
    'pass_reference',
    'passenger_name',
    'verification_token_hash',
    'pass_status',
    'booking_status',
    'flight_id',
    'flight_status',
    'flight_deleted',
    'flight_number',
    'destination',
    'seat_number',
    'departure_at',
    'expires_at',
    'lease_id',
    'offline_valid_until',
    'snapshot_signature',
    'is_enabled',
    'locally_used_at',
])]
class GatePassSnapshot extends Model
{
    protected $connection = 'gate_terminal';

    protected function casts(): array
    {
        return [
            'flight_deleted' => 'boolean',
            'departure_at' => 'datetime',
            'expires_at' => 'datetime',
            'offline_valid_until' => 'datetime',
            'is_enabled' => 'boolean',
            'locally_used_at' => 'datetime',
        ];
    }
}
