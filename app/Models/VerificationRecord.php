<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable([
    'boarding_pass_id',
    'passenger_id',
    'flight_id',
    'staff_id',
    'verification_reference',
    'result',
    'verification_method',
    'gate',
    'device_identifier',
    'verified_at',
    'notes',
    'sync_id',
    'sync_status',
    'approved_at',
])]
class VerificationRecord extends Model
{
    public const RESULT_VALID = 'VALID';

    public const RESULT_USED = 'USED';

    public const RESULT_EXPIRED = 'EXPIRED';

    public const RESULT_CANCELLED = 'CANCELLED';

    public const RESULT_INVALID = 'INVALID';

    public const RESULT_NOT_FOUND = 'NOT_FOUND';

    public const RESULTS = [
        self::RESULT_VALID,
        self::RESULT_USED,
        self::RESULT_EXPIRED,
        self::RESULT_CANCELLED,
        self::RESULT_INVALID,
        self::RESULT_NOT_FOUND,
    ];

    public const METHOD_ONLINE = 'ONLINE';

    public const METHOD_OFFLINE = 'OFFLINE';

    public const METHODS = [
        self::METHOD_ONLINE,
        self::METHOD_OFFLINE,
    ];

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
        ];
    }

    protected static function booted(): void
    {
        static::updating(static function (VerificationRecord $record): never {
            throw new LogicException('Verification records cannot be updated.');
        });
        static::deleting(static function (VerificationRecord $record): never {
            throw new LogicException('Verification records cannot be deleted.');
        });
    }

    public function boardingPass(): BelongsTo
    {
        return $this->belongsTo(BoardingPass::class);
    }

    public function passenger(): BelongsTo
    {
        return $this->belongsTo(User::class, 'passenger_id');
    }

    public function flight(): BelongsTo
    {
        return $this->belongsTo(Flight::class)->withTrashed();
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_id');
    }
}
