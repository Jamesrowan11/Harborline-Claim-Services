<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Automation extends Model
{
    use HasFactory, SoftDeletes, Auditable;

    protected $guarded = ['id'];
    protected $casts = [
        'conditions' => 'array', 'actions' => 'array',
        'is_sensitive' => 'boolean', 'approved_at' => 'datetime', 'paused' => 'boolean',
    ];

    public function runs() { return $this->hasMany(AutomationRun::class); }
    public function versions() { return $this->hasMany(AutomationVersion::class); }

    public function isRunnable(): bool
    {
        if ($this->paused || config('security.automation_global_stop')) {
            return false;
        }
        if ($this->is_sensitive && $this->approved_at === null) {
            return false;
        }
        return in_array($this->mode, ['active', 'test', 'approval'], true);
    }
}
