<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Document extends Model
{
    use HasFactory, SoftDeletes, Auditable;

    protected $guarded = ['id'];
    protected $casts = ['client_visible' => 'boolean', 'reviewed_at' => 'datetime'];

    public function case() { return $this->belongsTo(CaseFile::class, 'case_id'); }
    public function lead() { return $this->belongsTo(Lead::class); }
    public function claimant() { return $this->belongsTo(Claimant::class); }
    public function uploader() { return $this->morphTo('uploaded_by'); }
    public function reviewer() { return $this->belongsTo(User::class, 'reviewed_by'); }

    public function isSafeToServe(): bool
    {
        return $this->virus_scan_status !== 'infected';
    }
}
