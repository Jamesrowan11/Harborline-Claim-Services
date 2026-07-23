<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class LegalHold extends Model
{
    use Auditable;

    protected $guarded = ['id'];
    protected $casts = ['released_at' => 'datetime'];

    public function holdable() { return $this->morphTo(); }
}
