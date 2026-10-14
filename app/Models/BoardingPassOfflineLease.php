<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'boarding_pass_id',
    'gate_terminal_id',
    'lease_id',
    'status',
    'offline_valid_until',
    'consumed_at',
    'released_at',
])]
class BoardingPassOfflineLease extends Model
{
    public const STATUS_ACTIVE = 'ACTIVE';

    public const STATUS_CONSUMED = 'CONSUMED';

    public const STATUS_RELEASED = 'RELEASED';

    protected function casts(): array
    {
        return [
            'offline_valid_until' => 'datetime',
            'consumed_at' => 'datetime',
            'released_at' => 'datetime',
        ];
    }

    public function boardingPass(): BelongsTo
    {
        return $this->belongsTo(BoardingPass::class);
    }

    public function gateTerminal(): BelongsTo
    {
        return $this->belongsTo(GateTerminal::class);
    }
}
