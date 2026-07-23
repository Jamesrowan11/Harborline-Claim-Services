<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasRoles;

    protected $fillable = [
        'name', 'email', 'password', 'user_type', 'claimant_id', 'title', 'phone',
    ];

    protected $hidden = [
        'password', 'remember_token', 'mfa_secret', 'mfa_recovery_codes',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'mfa_secret' => 'encrypted',
            'mfa_recovery_codes' => 'encrypted:array',
            'mfa_enabled_at' => 'datetime',
            'last_login_at' => 'datetime',
            'deactivated_at' => 'datetime',
        ];
    }

    public function isStaff(): bool
    {
        return $this->user_type === 'staff';
    }

    public function isClient(): bool
    {
        return $this->user_type === 'client';
    }

    public function mfaEnabled(): bool
    {
        return $this->mfa_enabled_at !== null;
    }

    /** MFA is mandatory for all internal (staff) users. */
    public function mustEnrollMfa(): bool
    {
        return $this->isStaff()
            && config('security.mfa_required_for_staff', true)
            && ! $this->mfaEnabled();
    }

    public function claimant()
    {
        return $this->belongsTo(Claimant::class);
    }

    public function teams()
    {
        return $this->belongsToMany(Team::class);
    }

    public function assignedCases()
    {
        return $this->hasMany(CaseFile::class, 'assigned_to');
    }

    public function tasks()
    {
        return $this->hasMany(CaseTask::class, 'assigned_to');
    }
}
