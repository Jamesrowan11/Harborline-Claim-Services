<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\AuditEvent;
use App\Models\CaseFile;
use App\Models\Lead;
use App\Services\Settings;
use Illuminate\Http\Request;

/**
 * Public letter verification. Confirms ONLY that a reference number is
 * authentic and how to safely reach us. Never exposes surplus amounts,
 * property details, or claimant data.
 */
class VerifyLetterController extends Controller
{
    public function show()
    {
        return view('site.verify');
    }

    public function check(Request $request)
    {
        $data = $request->validate([
            'reference' => ['required', 'string', 'max:40'],
            'last_name' => ['required', 'string', 'max:80'],
            'zip' => ['required', 'string', 'max:10'],
        ]);

        $result = $this->lookup($data['reference'], $data['last_name'], $data['zip']);

        AuditEvent::record('letter_verification_attempt', null, [], [
            'reference' => $data['reference'],
            'matched' => $result !== null,
        ]);

        return view('site.verify', [
            'checked' => true,
            'result' => $result,
        ]);
    }

    private function lookup(string $reference, string $lastName, string $zip): ?array
    {
        $reference = strtoupper(trim($reference));
        $zip = substr(preg_replace('/[^0-9]/', '', $zip), 0, 5);

        $case = CaseFile::query()->where('case_number', $reference)->first();
        if ($case) {
            $nameMatch = $case->claimants()->get()->contains(
                fn ($c) => strcasecmp((string) $c->last_name, $lastName) === 0);
            $zipMatch = $case->property && str_starts_with((string) $case->property->zip, $zip);
            if ($nameMatch && ($zipMatch || $case->property === null)) {
                return [
                    'reference' => $case->case_number,
                    'representative' => $case->caseManager?->name ?? $case->assignee?->name ?? __('Our intake team'),
                ];
            }
        }

        $lead = Lead::query()->where('lead_number', $reference)->first();
        if ($lead && strcasecmp((string) $lead->last_name, $lastName) === 0) {
            return [
                'reference' => $lead->lead_number,
                'representative' => $lead->assignee?->name ?? __('Our intake team'),
            ];
        }

        return null;
    }
}
