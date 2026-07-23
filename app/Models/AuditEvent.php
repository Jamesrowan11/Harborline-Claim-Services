<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Append-only audit trail. Never expose update/delete routes for this model;
 * retention is handled only by the retention policy console command, which
 * refuses to touch records under legal hold.
 */
class AuditEvent extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'created_at' => 'datetime',
    ];

    public static function record(string $event, ?Model $auditable = null, array $old = [], array $new = [], array $extra = []): self
    {
        return static::query()->create(array_merge([
            'user_id' => Auth::id(),
            'event' => $event,
            'auditable_type' => $auditable?->getMorphClass(),
            'auditable_id' => $auditable?->getKey(),
            'case_id' => $extra['case_id'] ?? ($auditable->case_id ?? ($auditable instanceof CaseFile ? $auditable->id : null)),
            'old_values' => $old ?: null,
            'new_values' => $new ?: null,
            'ip_address' => request()?->ip(),
            'user_agent' => substr((string) request()?->userAgent(), 0, 255) ?: null,
            'created_at' => now(),
        ], array_diff_key($extra, ['case_id' => true])));
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
