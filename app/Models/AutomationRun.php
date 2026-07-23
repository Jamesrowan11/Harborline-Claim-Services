<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AutomationRun extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['context' => 'array', 'started_at' => 'datetime', 'finished_at' => 'datetime'];

    public function automation() { return $this->belongsTo(Automation::class); }
    public function case() { return $this->belongsTo(CaseFile::class, 'case_id'); }
}
