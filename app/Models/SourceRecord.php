<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SourceRecord extends Model
{
    use HasFactory, Auditable;

    protected $guarded = ['id'];
    protected $casts = ['record_date' => 'date', 'retrieved_at' => 'datetime', 'stale_after' => 'datetime'];

    public function case() { return $this->belongsTo(CaseFile::class, 'case_id'); }

    public function isStale(): bool
    {
        return $this->status === 'stale' || ($this->stale_after !== null && $this->stale_after->isPast());
    }
}
