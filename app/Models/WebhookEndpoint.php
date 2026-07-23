<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class WebhookEndpoint extends Model
{
    use Auditable;

    protected $guarded = ['id'];
    protected $casts = ['secret' => 'encrypted', 'active' => 'boolean'];
}
