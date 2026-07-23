<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StageTransition extends Model
{
    public const UPDATED_AT = null;
    protected $guarded = ['id'];
    protected $casts = ['created_at' => 'datetime'];

    public function case() { return $this->belongsTo(CaseFile::class, 'case_id'); }
    public function fromStage() { return $this->belongsTo(PipelineStage::class, 'from_stage_id'); }
    public function toStage() { return $this->belongsTo(PipelineStage::class, 'to_stage_id'); }
    public function user() { return $this->belongsTo(User::class); }
}
