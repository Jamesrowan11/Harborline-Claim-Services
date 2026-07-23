<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Claimant extends Model
{
    use HasFactory, SoftDeletes, Auditable;

    protected $guarded = ['id'];

    protected $casts = [
        'prior_names' => 'array',
        'date_of_birth' => 'encrypted',
        'ssn_last_four' => 'encrypted',
        'is_deceased' => 'boolean',
        'date_of_death' => 'date',
        'identity_verified_at' => 'datetime',
    ];

    /** Fields that must never be serialized to the client portal. */
    public const STAFF_ONLY_FIELDS = ['notes_internal', 'ssn_last_four', 'date_of_birth', 'identity_verification_status'];

    public function cases() { return $this->belongsToMany(CaseFile::class, 'case_claimant', 'claimant_id', 'case_id')->withPivot(['role', 'share_percent'])->withTimestamps(); }
    public function user() { return $this->hasOne(User::class); }
    public function addresses() { return $this->morphMany(Address::class, 'addressable'); }
    public function phoneNumbers() { return $this->morphMany(PhoneNumber::class, 'phoneable'); }
    public function emailAddresses() { return $this->morphMany(EmailAddress::class, 'emailable'); }
    public function consentRecords() { return $this->morphMany(ConsentRecord::class, 'consentable'); }
    public function documents() { return $this->hasMany(Document::class); }

    public function displayName(): string
    {
        return $this->organization_name ?: trim("{$this->first_name} {$this->last_name}");
    }
}
