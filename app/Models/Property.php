<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Property extends Model
{
    use HasFactory, Auditable;

    protected $guarded = ['id'];

    protected $casts = ['former_owner_names' => 'array',];
}
