<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Deadline extends Model
{
    use HasFactory, Auditable;

    protected $guarded = ['id'];
    protected $casts = ['due_at' => 'datetime', 'is_critical' => 'boolean', 'met_at' => 'datetime'];

    public function case() { return $this->belongsTo(CaseFile::class, 'case_id'); }
}
