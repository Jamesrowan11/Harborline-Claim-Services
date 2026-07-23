<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Sale extends Model
{
    use HasFactory, Auditable;

    protected $guarded = ['id'];
    protected $casts = ['sale_date' => 'date', 'sale_price' => 'decimal:2', 'opening_bid' => 'decimal:2'];

    public function property() { return $this->belongsTo(Property::class); }
    public function auction() { return $this->belongsTo(Auction::class); }
    public function trustee() { return $this->belongsTo(Trustee::class); }
}
