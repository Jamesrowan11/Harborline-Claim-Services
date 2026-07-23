<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PortalMessage extends Model
{
    use HasFactory;

    protected $guarded = ['id'];
    protected $casts = ['read_at' => 'datetime'];

    public function case() { return $this->belongsTo(CaseFile::class, 'case_id'); }
    public function sender() { return $this->belongsTo(User::class, 'user_id'); }
}
