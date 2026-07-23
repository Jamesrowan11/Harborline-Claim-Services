<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SurplusRecord extends Model
{
    use HasFactory, Auditable;

    protected $guarded = ['id'];
    protected $casts = [
        'estimated_amount' => 'decimal:2', 'verified_amount' => 'decimal:2',
        'verified_at' => 'datetime', 'verification_expires_at' => 'datetime',
    ];

    public function case() { return $this->belongsTo(CaseFile::class, 'case_id'); }
    public function sale() { return $this->belongsTo(Sale::class); }
    public function fundsHolder() { return $this->belongsTo(FundsHolder::class); }
    public function verifier() { return $this->belongsTo(User::class, 'verified_by'); }

    public function isStale(): bool
    {
        return $this->verification_expires_at !== null && $this->verification_expires_at->isPast();
    }
}
