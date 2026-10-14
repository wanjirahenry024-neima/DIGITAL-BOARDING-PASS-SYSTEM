<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'terminal_id',
    'name',
    'gate',
    'api_token_hash',
    'api_token',
    'is_active',
    'last_seen_at',
    'revoked_at',
])]
#[Hidden(['api_token', 'api_token_hash'])]
class GateTerminal extends Model
{
    protected function casts(): array
    {
        return [
            'api_token' => 'encrypted',
            'is_active' => 'boolean',
            'last_seen_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function offlineLeases(): HasMany
    {
        return $this->hasMany(BoardingPassOfflineLease::class);
    }
}
