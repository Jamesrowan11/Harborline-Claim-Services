<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AutomationVersion extends Model
{
    public const UPDATED_AT = null;
    protected $guarded = ['id'];
    protected $casts = ['definition' => 'array', 'created_at' => 'datetime'];

    public function automation() { return $this->belongsTo(Automation::class); }
}
