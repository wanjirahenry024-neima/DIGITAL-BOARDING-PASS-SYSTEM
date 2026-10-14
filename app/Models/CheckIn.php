<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'booking_id',
    'passenger_id',
    'flight_id',
    'check_in_reference',
    'checked_in_at',
    'seat_number',
    'status',
])]
class CheckIn extends Model
{
    public const STATUS_CHECKED_IN = 'Checked In';

    protected function casts(): array
    {
        return [
            'checked_in_at' => 'datetime',
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

    public function boardingPass(): HasOne
    {
        return $this->hasOne(BoardingPass::class);
    }
}
