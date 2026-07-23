<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocumentRequest extends Model
{
    use HasFactory, Auditable;

    protected $guarded = ['id'];
    protected $casts = ['due_at' => 'datetime'];

    public function case() { return $this->belongsTo(CaseFile::class, 'case_id'); }
    public function claimant() { return $this->belongsTo(Claimant::class); }
    public function fulfilledDocument() { return $this->belongsTo(Document::class, 'fulfilled_document_id'); }
}
