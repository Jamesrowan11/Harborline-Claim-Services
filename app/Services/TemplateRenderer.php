<?php

namespace App\Services;

use App\Models\CaseFile;
use App\Models\Claimant;
use App\Models\DocumentTemplate;

/**
 * Renders {{merge_field}} placeholders in communication and letter templates.
 * Only whitelisted fields are available — templates can never reach arbitrary
 * model attributes or staff-only data.
 */
class TemplateRenderer
{
    public function render(DocumentTemplate $template, ?CaseFile $case = null, ?Claimant $claimant = null, array $extra = []): array
    {
        $fields = array_merge($this->mergeFields($case, $claimant), $extra);

        $replace = fn (?string $text) => preg_replace_callback(
            '/\{\{\s*([a-z0-9_.]+)\s*\}\}/i',
            fn ($m) => (string) ($fields[strtolower($m[1])] ?? ''),
            (string) $text,
        );

        return [
            'subject' => $replace($template->subject),
            'body' => $replace($template->body),
            'version' => $template->version,
        ];
    }

    public function mergeFields(?CaseFile $case, ?Claimant $claimant): array
    {
        return [
            'brand.name' => Settings::brand('name'),
            'brand.legal_name' => Settings::brand('legal_name'),
            'brand.phone' => Settings::brand('phone'),
            'brand.email' => Settings::brand('email'),
            'brand.address' => Settings::brand('address'),
            'brand.disclaimer' => config('branding.disclaimer'),
            'case.number' => $case?->case_number ?? '',
            'case.county' => $case?->county ?? '',
            'case.state' => $case?->state ?? '',
            'case.status_label' => $case?->clientStatusLabel() ?? '',
            'case.manager' => $case?->caseManager?->name ?? '',
            'claimant.name' => $claimant?->displayName() ?? '',
            'claimant.first_name' => $claimant?->first_name ?? '',
            'claimant.last_name' => $claimant?->last_name ?? '',
            'today' => now()->format('F j, Y'),
        ];
    }
}
