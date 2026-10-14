<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'flight_number',
    'airline',
    'departure_airport',
    'arrival_airport',
    'departure_date',
    'departure_time',
    'arrival_time',
    'aircraft',
    'total_seats',
    'available_seats',
    'status',
])]
class Flight extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_SCHEDULED = 'Scheduled';

    public const STATUS_BOARDING = 'Boarding';

    public const STATUS_DEPARTED = 'Departed';

    public const STATUS_CANCELLED = 'Cancelled';

    public const STATUS_COMPLETED = 'Completed';

    public const STATUSES = [
        self::STATUS_SCHEDULED,
        self::STATUS_BOARDING,
        self::STATUS_DEPARTED,
        self::STATUS_CANCELLED,
        self::STATUS_COMPLETED,
    ];

    protected function casts(): array
    {
        return [
            'departure_date' => 'date',
        ];
    }

    public function scopeAvailableForPassengers(Builder $query): Builder
    {
        return $query
            ->where('available_seats', '>', 0)
            ->whereIn('status', [self::STATUS_SCHEDULED, self::STATUS_BOARDING]);
    }

    public function checkIns(): HasMany
    {
        return $this->hasMany(CheckIn::class);
    }

    public function verificationRecords(): HasMany
    {
        return $this->hasMany(VerificationRecord::class);
    }
}
