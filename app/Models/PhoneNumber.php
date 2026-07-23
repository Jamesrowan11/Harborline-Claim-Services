<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PhoneNumber extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['sms_capable' => 'boolean', 'do_not_call' => 'boolean'];

    public function phoneable() { return $this->morphTo(); }
}
