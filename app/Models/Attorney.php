<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Attorney extends Model
{
    use HasFactory, Auditable;

    protected $guarded = ['id'];

    protected $casts = ['practice_areas' => 'array', 'accepts_referrals' => 'boolean',];
}
