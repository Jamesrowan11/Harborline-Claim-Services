<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class PipelineStage extends Model
{
    use Auditable;

    protected $guarded = ['id'];

    protected $casts = [
        'is_closed' => 'boolean',
        'is_hold' => 'boolean',
        'requires_compliance_review' => 'boolean',
        'requires_attorney_review' => 'boolean',
        'active' => 'boolean',
    ];

    public function cases() { return $this->hasMany(CaseFile::class); }
}
