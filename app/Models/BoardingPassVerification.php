<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'boarding_pass_id',
    'verification_record_id',
    'staff_id',
    'approved_by',
    'pass_reference',
    'status',
    'reason',
    'verified_at',
    'approved_at',
])]
class BoardingPassVerification extends Model
{
    public const STATUS_VALID = 'VALID';

    public const STATUS_USED = 'USED';

    public const STATUS_EXPIRED = 'EXPIRED';

    public const STATUS_CANCELLED = 'CANCELLED';

    public const STATUS_INVALID = 'INVALID';

    public const STATUS_NOT_FOUND = 'NOT_FOUND';

    public const STATUS_CONFLICT = 'CONFLICT';

    public const STATUSES = [
        self::STATUS_VALID,
        self::STATUS_USED,
        self::STATUS_EXPIRED,
        self::STATUS_CANCELLED,
        self::STATUS_INVALID,
        self::STATUS_NOT_FOUND,
        self::STATUS_CONFLICT,
    ];

    protected function casts(): array
    {
        return [
            'verified_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    public function boardingPass(): BelongsTo
    {
        return $this->belongsTo(BoardingPass::class);
    }

    public function verificationRecord(): BelongsTo
    {
        return $this->belongsTo(VerificationRecord::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
