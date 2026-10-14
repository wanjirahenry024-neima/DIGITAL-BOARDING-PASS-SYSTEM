<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['full_name', 'email', 'phone', 'password', 'role', 'status', 'central_user_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'central_user_id' => 'integer',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'passenger_id');
    }

    public function checkIns(): HasMany
    {
        return $this->hasMany(CheckIn::class, 'passenger_id');
    }

    public function boardingPasses(): HasMany
    {
        return $this->hasMany(BoardingPass::class, 'passenger_id');
    }

    public function boardingPassVerifications(): HasMany
    {
        return $this->hasMany(BoardingPassVerification::class, 'staff_id');
    }

    public function verificationRecords(): HasMany
    {
        return $this->hasMany(VerificationRecord::class, 'passenger_id');
    }

    public function staffVerificationRecords(): HasMany
    {
        return $this->hasMany(VerificationRecord::class, 'staff_id');
    }
}
