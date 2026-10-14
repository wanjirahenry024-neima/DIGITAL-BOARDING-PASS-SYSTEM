<?php

namespace App\Models;

use Database\Factories\BookingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'booking_reference',
    'passenger_id',
    'flight_id',
    'booking_status',
    'booking_date',
    'number_of_passengers',
])]
class Booking extends Model
{
    /** @use HasFactory<BookingFactory> */
    use HasFactory;

    public const STATUS_CONFIRMED = 'Confirmed';

    public const STATUS_CANCELLED = 'Cancelled';

    public const STATUSES = [
        self::STATUS_CONFIRMED,
        self::STATUS_CANCELLED,
    ];

    protected function casts(): array
    {
        return [
            'booking_date' => 'datetime',
        ];
    }

    public function passenger(): BelongsTo
    {
        return $this->belongsTo(User::class, 'passenger_id');
    }

    public function flight(): BelongsTo
    {
        return $this->belongsTo(Flight::class)->withTrashed();
    }

    public function checkIn(): HasOne
    {
        return $this->hasOne(CheckIn::class);
    }

    public function boardingPass(): HasOne
    {
        return $this->hasOne(BoardingPass::class);
    }
}
