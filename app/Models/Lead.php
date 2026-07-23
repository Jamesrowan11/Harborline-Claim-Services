<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Lead extends Model
{
    use HasFactory, SoftDeletes, Auditable;

    protected $guarded = ['id'];

    protected $casts = [
        'former_owner_living' => 'boolean',
        'consent_to_contact' => 'boolean',
        'privacy_policy_agreed' => 'boolean',
        'electronic_consent' => 'boolean',
        'sms_consent' => 'boolean',
    ];

    public function assignee() { return $this->belongsTo(User::class, 'assigned_to'); }
    public function duplicateOf() { return $this->belongsTo(Lead::class, 'duplicate_of_lead_id'); }
    public function case() { return $this->belongsTo(CaseFile::class, 'case_id'); }
    public function tasks() { return $this->hasMany(CaseTask::class, 'lead_id'); }
    public function consentRecords() { return $this->morphMany(ConsentRecord::class, 'consentable'); }
    public function notes() { return $this->morphMany(Note::class, 'notable'); }

    public function fullName(): string
    {
        return trim("{$this->first_name} {$this->middle_name} {$this->last_name}");
    }
}
