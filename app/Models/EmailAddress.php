<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailAddress extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['bounced' => 'boolean', 'opted_out' => 'boolean'];

    public function emailable() { return $this->morphTo(); }
}
