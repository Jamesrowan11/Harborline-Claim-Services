<?php

namespace App\Models\Concerns;

use App\Models\AuditEvent;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * Records immutable audit events for create/update/delete on any model using it.
 * Sensitive attributes are masked; audit rows are append-only (no update/delete
 * path exists anywhere in the application for audit_events).
 */
trait Auditable
{
    /** Attributes whose values must never appear in audit payloads. */
    protected array $auditMasked = [
        'password', 'ssn_last_four', 'date_of_birth', 'mfa_secret',
        'mfa_recovery_codes', 'remember_token', 'secret',
    ];

    public static function bootAuditable(): void
    {
        static::created(fn ($model) => $model->writeAudit('created', [], $model->auditPayload($model->getAttributes())));
        static::updated(fn ($model) => $model->writeAudit(
            'updated',
            $model->auditPayload(array_intersect_key($model->getOriginal(), $model->getDirty())),
            $model->auditPayload($model->getDirty()),
        ));
        static::deleted(fn ($model) => $model->writeAudit('deleted', $model->auditPayload($model->getAttributes()), []));
    }

    public function auditPayload(array $attributes): array
    {
        foreach ($this->auditMasked as $masked) {
            if (array_key_exists($masked, $attributes)) {
                $attributes[$masked] = '[redacted]';
            }
        }
        unset($attributes['updated_at'], $attributes['created_at']);

        return $attributes;
    }

    public function writeAudit(string $event, array $old = [], array $new = []): void
    {
        AuditEvent::record($event, $this, $old, $new);
    }
}
