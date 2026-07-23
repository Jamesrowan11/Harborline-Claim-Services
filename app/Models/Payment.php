<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory, Auditable;

    protected $guarded = ['id'];
    protected $casts = ['amount' => 'decimal:2', 'occurred_on' => 'date'];

    public function case() { return $this->belongsTo(CaseFile::class, 'case_id'); }
    public function claim() { return $this->belongsTo(Claim::class); }
    public function recorder() { return $this->belongsTo(User::class, 'recorded_by'); }
}
