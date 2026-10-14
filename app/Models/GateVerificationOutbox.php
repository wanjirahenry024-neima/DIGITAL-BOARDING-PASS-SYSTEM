<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'idempotency_key',
    'boarding_pass_id',
    'flight_id',
    'passenger_id',
    'staff_id',
    'lease_id',
    'verification_reference',
    'result',
    'verification_method',
    'gate',
    'device_identifier',
    'verified_at',
    'notes',
    'approved_at',
    'server_verification_record_id',
    'sync_status',
    'sync_attempts',
    'next_attempt_at',
    'last_sync_error',
])]
class GateVerificationOutbox extends Model
{
    protected $connection = 'gate_terminal';

    protected $table = 'gate_verification_outbox';

    public const SYNC_PENDING = 'PENDING';

    public const SYNC_SYNCING = 'SYNCING';

    public const SYNC_SYNCED = 'SYNCED';

    public const SYNC_FAILED = 'FAILED';

    public const SYNC_CONFLICT = 'CONFLICT';

    protected function casts(): array
    {
        return [
            'verified_at' => 'datetime',
            'approved_at' => 'datetime',
            'next_attempt_at' => 'datetime',
        ];
    }
}
