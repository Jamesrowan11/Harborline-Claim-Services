<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * [ATTORNEY REVIEW REQUIRED] Agreement contents (fee model, assignment
 * language, powers of attorney) must be drafted and approved by licensed
 * counsel before use. The application enforces approval workflow only.
 */
class Agreement extends Model
{
    use HasFactory, Auditable;

    protected $guarded = ['id'];
    protected $casts = [
        'fee_percent' => 'decimal:2', 'fee_flat' => 'decimal:2',
        'sent_at' => 'datetime', 'signed_at' => 'datetime',
    ];

    public function case() { return $this->belongsTo(CaseFile::class, 'case_id'); }
    public function claimant() { return $this->belongsTo(Claimant::class); }
    public function template() { return $this->belongsTo(DocumentTemplate::class, 'document_template_id'); }
}
