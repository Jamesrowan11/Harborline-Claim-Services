<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OptOut extends Model
{
    use HasFactory, Auditable;

    protected $guarded = ['id'];
    protected $casts = ['occurred_at' => 'datetime'];

    public function optoutable() { return $this->morphTo(); }

    public static function exists_for(string $channel, ?string $value): bool
    {
        if ($value === null || $value === '') {
            return false;
        }

        return static::query()
            ->where('value', $value)
            ->whereIn('channel', [$channel, 'all'])
            ->exists();
    }
}
