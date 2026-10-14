<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AuditLogService
{
    /**
     * @param  array<string, mixed>  $properties
     */
    public function record(
        string $event,
        ?User $actor = null,
        ?Model $subject = null,
        ?string $description = null,
        array $properties = [],
    ): AuditLog {
        $request = request();

        return AuditLog::create([
            'actor_id' => $actor?->getKey(),
            'event' => $event,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'description' => $description,
            'properties' => $properties === [] ? null : $properties,
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 2000, ''),
            'occurred_at' => now(),
        ]);
    }
}
