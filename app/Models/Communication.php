<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Communication extends Model
{
    use HasFactory, Auditable;

    protected $guarded = ['id'];
    protected $casts = ['approved_at' => 'datetime', 'sent_at' => 'datetime', 'meta' => 'array'];

    public function case() { return $this->belongsTo(CaseFile::class, 'case_id'); }
    public function lead() { return $this->belongsTo(Lead::class); }
    public function claimant() { return $this->belongsTo(Claimant::class); }
    public function template() { return $this->belongsTo(DocumentTemplate::class, 'document_template_id'); }
    public function generator() { return $this->belongsTo(User::class, 'generated_by'); }
    public function approver() { return $this->belongsTo(User::class, 'approved_by'); }
}
