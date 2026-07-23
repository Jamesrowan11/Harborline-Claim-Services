<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CaseTask extends Model
{
    use HasFactory, Auditable;

    protected $guarded = ['id'];
    protected $casts = ['due_at' => 'datetime', 'completed_at' => 'datetime', 'client_visible' => 'boolean'];

    public function case() { return $this->belongsTo(CaseFile::class, 'case_id'); }
    public function lead() { return $this->belongsTo(Lead::class); }
    public function assignee() { return $this->belongsTo(User::class, 'assigned_to'); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }

    public function isOverdue(): bool
    {
        return $this->status !== 'done' && $this->due_at !== null && $this->due_at->isPast();
    }
}
