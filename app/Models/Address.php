<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Address extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['is_current' => 'boolean', 'returned_mail' => 'boolean'];

    public function addressable() { return $this->morphTo(); }

    public function oneLine(): string
    {
        return trim("{$this->line1} {$this->line2}, {$this->city}, {$this->state} {$this->zip}", ' ,');
    }
}
