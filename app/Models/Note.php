<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class Note extends Model
{
    use Auditable;

    protected $guarded = ['id'];
    protected $casts = ['is_staff_only' => 'boolean'];

    public function notable() { return $this->morphTo(); }
    public function author() { return $this->belongsTo(User::class, 'user_id'); }
}
