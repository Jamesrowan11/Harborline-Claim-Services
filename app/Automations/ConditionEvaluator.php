<?php

namespace App\Automations;

use App\Models\CaseFile;
use App\Models\Lead;

/**
 * Evaluates automation conditions: [{field, operator, value}]. All conditions
 * must pass (AND). Fields resolve against the case, then lead, then event
 * context, including custom fields (custom.*).
 */
class ConditionEvaluator
{
    public static function passes(array $conditions, ?CaseFile $case, ?Lead $lead, array $context = []): bool
    {
        foreach ($conditions as $condition) {
            $actual = static::resolve($condition['field'] ?? '', $case, $lead, $context);
            if (! static::compare($actual, $condition['operator'] ?? 'equals', $condition['value'] ?? null)) {
                return false;
            }
        }

        return true;
    }

    public static function resolve(string $field, ?CaseFile $case, ?Lead $lead, array $context): mixed
    {
        if (array_key_exists($field, $context)) {
            return $context[$field];
        }

        if (str_starts_with($field, 'custom.')) {
            return data_get($case?->custom_fields, substr($field, 7));
        }

        return match ($field) {
            'county' => $case?->county ?? $lead?->property_county,
            'state' => $case?->state ?? $lead?->property_state,
            'case_type' => $case?->case_type,
            'sale_type' => $case?->sale?->sale_type,
            'surplus_amount' => $case?->verified_surplus ?? $case?->estimated_surplus,
            'verification_level' => $case?->verification_level,
            'assigned_employee' => $case?->assignee?->email ?? $lead?->assignee?->email,
            'risk_level' => $case?->risk_level,
            'legal_complexity' => $case?->legal_complexity,
            'missing_documents' => $case?->documentRequests()->whereIn('status', ['requested'])->exists(),
            'consent_status' => $lead?->consent_to_contact ?? $case?->lead?->consent_to_contact,
            'stage' => $case?->stage?->key,
            'days_since_last_activity' => $case?->last_activity_at?->diffInDays(now()),
            'outreach_approved' => $case?->outreach_approved,
            default => data_get($case, $field) ?? data_get($lead, $field),
        };
    }

    public static function compare(mixed $actual, string $operator, mixed $expected): bool
    {
        return match ($operator) {
            'equals' => $actual == $expected,
            'not_equals' => $actual != $expected,
            'greater_than' => is_numeric($actual) && (float) $actual > (float) $expected,
            'less_than' => is_numeric($actual) && (float) $actual < (float) $expected,
            'contains' => is_string($actual) && str_contains(strtolower($actual), strtolower((string) $expected)),
            'in' => in_array($actual, (array) $expected),
            'not_in' => ! in_array($actual, (array) $expected),
            'is_true' => (bool) $actual === true,
            'is_false' => (bool) $actual === false,
            'is_empty' => $actual === null || $actual === '' || $actual === [],
            'is_not_empty' => ! ($actual === null || $actual === '' || $actual === []),
            default => false,
        };
    }
}
