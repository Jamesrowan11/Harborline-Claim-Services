<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocumentTemplate extends Model
{
    use HasFactory, Auditable;

    protected $guarded = ['id'];
    protected $casts = ['requires_attorney_review' => 'boolean', 'approved_at' => 'datetime', 'active' => 'boolean'];

    public function versions() { return $this->hasMany(DocumentTemplateVersion::class); }

    public function isApproved(): bool
    {
        return $this->approval_status === 'approved';
    }
}
