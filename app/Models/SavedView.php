<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SavedView extends Model
{
    use HasFactory, Auditable;

    protected $guarded = ['id'];

    protected $casts = ['config' => 'array', 'is_shared' => 'boolean',];
}
