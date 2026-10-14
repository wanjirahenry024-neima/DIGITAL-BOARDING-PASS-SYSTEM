<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'pass_reference',
    'booking_id',
    'passenger_id',
    'flight_id',
    'check_in_id',
    'seat_number',
    'pass_status',
    'issued_at',
    'expires_at',
    'used_at',
    'verification_token',
    'verification_token_hash',
])]
#[Hidden(['verification_token', 'verification_token_hash'])]
class BoardingPass extends Model
{
    public const QR_FORMAT_VERSION = 'DBP1';

    public const STATUS_ACTIVE = 'ACTIVE';

    public const STATUS_USED = 'USED';

    public const STATUS_CANCELLED = 'CANCELLED';

    public const STATUS_EXPIRED = 'EXPIRED';

    public const STATUS_INVALID = 'INVALID';

    public const STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_USED,
        self::STATUS_CANCELLED,
        self::STATUS_EXPIRED,
        self::STATUS_INVALID,
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
            'verification_token' => 'encrypted',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function passenger(): BelongsTo
    {
        return $this->belongsTo(User::class, 'passenger_id');
    }

    public function flight(): BelongsTo
    {
        return $this->belongsTo(Flight::class)->withTrashed();
    }

    public function checkIn(): BelongsTo
    {
        return $this->belongsTo(CheckIn::class);
    }

    public function verifications(): HasMany
    {
        return $this->hasMany(BoardingPassVerification::class);
    }

    public function verificationRecords(): HasMany
    {
        return $this->hasMany(VerificationRecord::class);
    }
}
