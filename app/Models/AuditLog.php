<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

#[Fillable([
    'actor_id',
    'event',
    'subject_type',
    'subject_id',
    'description',
    'properties',
    'ip_address',
    'user_agent',
    'occurred_at',
])]
class AuditLog extends Model
{
    public const EVENT_LOGIN = 'login';

    public const EVENT_LOGIN_FAILED = 'login_failed';

    public const EVENT_LOGOUT = 'logout';

    public const EVENT_BOOKING_CREATED = 'booking_created';

    public const EVENT_BOOKING_CANCELLED = 'booking_cancelled';

    public const EVENT_CHECK_IN = 'check_in';

    public const EVENT_BOARDING_PASS_CREATED = 'boarding_pass_created';

    public const EVENT_QR_VERIFICATION = 'qr_verification';

    public const EVENT_FAILED_VERIFICATION = 'failed_verification';

    public const EVENT_BOARDING_APPROVED = 'boarding_approved';

    public const EVENT_ADMINISTRATOR_CHANGE = 'administrator_change';

    protected function casts(): array
    {
        return [
            'properties' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(static function (AuditLog $auditLog): never {
            throw new LogicException('Audit log records cannot be updated.');
        });
        static::deleting(static function (AuditLog $auditLog): never {
            throw new LogicException('Audit log records cannot be deleted.');
        });
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function subject(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'subject_type', 'subject_id');
    }
}
