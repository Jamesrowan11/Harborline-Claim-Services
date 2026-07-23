<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A surplus-recovery case. Model is named CaseFile because "case" is a
 * reserved word in PHP; the underlying table is "cases".
 */
class CaseFile extends Model
{
    use HasFactory, SoftDeletes, Auditable;

    protected $table = 'cases';
    protected $guarded = ['id'];

    protected $casts = [
        'estimated_surplus' => 'decimal:2',
        'verified_surplus' => 'decimal:2',
        'compliance_hold' => 'boolean',
        'legal_hold' => 'boolean',
        'automation_paused' => 'boolean',
        'outreach_approved' => 'boolean',
        'last_activity_at' => 'datetime',
        'closed_at' => 'datetime',
        'custom_fields' => 'array',
    ];

    public function stage() { return $this->belongsTo(PipelineStage::class, 'pipeline_stage_id'); }
    public function lead() { return $this->belongsTo(Lead::class); }
    public function property() { return $this->belongsTo(Property::class); }
    public function sale() { return $this->belongsTo(Sale::class); }
    public function courtCase() { return $this->belongsTo(CourtCase::class); }
    public function fundsHolder() { return $this->belongsTo(FundsHolder::class); }
    public function assignee() { return $this->belongsTo(User::class, 'assigned_to'); }
    public function caseManager() { return $this->belongsTo(User::class, 'case_manager_id'); }
    public function attorney() { return $this->belongsTo(Attorney::class); }
    public function claimants() { return $this->belongsToMany(Claimant::class, 'case_claimant', 'case_id', 'claimant_id')->withPivot(['role', 'share_percent'])->withTimestamps(); }
    public function surplusRecords() { return $this->hasMany(SurplusRecord::class, 'case_id'); }
    public function sourceRecords() { return $this->hasMany(SourceRecord::class, 'case_id'); }
    public function tasks() { return $this->hasMany(CaseTask::class, 'case_id'); }
    public function deadlines() { return $this->hasMany(Deadline::class, 'case_id'); }
    public function documents() { return $this->hasMany(Document::class, 'case_id'); }
    public function documentRequests() { return $this->hasMany(DocumentRequest::class, 'case_id'); }
    public function communications() { return $this->hasMany(Communication::class, 'case_id'); }
    public function agreements() { return $this->hasMany(Agreement::class, 'case_id'); }
    public function claims() { return $this->hasMany(Claim::class, 'case_id'); }
    public function payments() { return $this->hasMany(Payment::class, 'case_id'); }
    public function expenses() { return $this->hasMany(Expense::class, 'case_id'); }
    public function notes() { return $this->morphMany(Note::class, 'notable'); }
    public function stageTransitions() { return $this->hasMany(StageTransition::class, 'case_id'); }
    public function portalMessages() { return $this->hasMany(PortalMessage::class, 'case_id'); }

    public function isOnHold(): bool
    {
        return $this->compliance_hold || $this->legal_hold || ($this->stage?->is_hold ?? false);
    }

    public function clientStatusLabel(): string
    {
        return $this->stage?->client_label ?? 'Information Received';
    }

    public function touchActivity(): void
    {
        $this->forceFill(['last_activity_at' => now()])->saveQuietly();
    }
}
