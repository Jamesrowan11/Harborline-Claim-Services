<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\CaseFile;
use App\Models\Claimant;
use App\Models\Lead;
use App\Models\Property;
use App\Models\SourceRecord;
use Illuminate\Http\Request;

/**
 * Global search across cases, leads, claimants, properties, and source
 * records — case numbers, names, prior names, addresses, parcel/tax numbers,
 * court case numbers, phones, emails, attorneys, trustees, counties.
 */
class SearchController extends Controller
{
    public function __invoke(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $term = '%'.$q.'%';

        if ($q === '') {
            return view('portal.search', ['q' => $q, 'results' => collect()]);
        }

        $results = collect();

        if ($request->user()->can('cases.view')) {
            $results = $results->merge(CaseFile::query()->with('stage')
                ->where('case_number', 'like', $term)
                ->orWhere('county', 'like', $term)
                ->orWhereHas('courtCase', fn ($c) => $c->where('court_case_number', 'like', $term))
                ->orWhereHas('attorney', fn ($a) => $a->where('name', 'like', $term))
                ->limit(20)->get()
                ->map(fn ($c) => ['type' => 'Case', 'label' => $c->case_number.' — '.($c->county ?? ''), 'url' => route('portal.cases.show', $c)]));

            $results = $results->merge(Property::query()
                ->where('address_line1', 'like', $term)
                ->orWhere('parcel_number', 'like', $term)
                ->orWhere('tax_account_number', 'like', $term)
                ->limit(20)->get()
                ->map(fn ($p) => ['type' => 'Property', 'label' => $p->address_line1.', '.$p->county, 'url' => route('portal.cases.index', ['q' => $p->parcel_number ?: $p->address_line1])]));

            $results = $results->merge(Claimant::query()
                ->where('first_name', 'like', $term)
                ->orWhere('last_name', 'like', $term)
                ->orWhere('organization_name', 'like', $term)
                ->orWhere('prior_names', 'like', $term)
                ->orWhereHas('emailAddresses', fn ($e) => $e->where('email', 'like', $term))
                ->orWhereHas('phoneNumbers', fn ($p) => $p->where('number', 'like', $term))
                ->limit(20)->get()
                ->map(function ($claimant) {
                    $case = $claimant->cases()->first();
                    return ['type' => 'Claimant', 'label' => $claimant->displayName(),
                        'url' => $case ? route('portal.cases.show', $case) : route('portal.cases.index', ['q' => $claimant->last_name])];
                }));

            $results = $results->merge(SourceRecord::query()->with('case')
                ->where('title', 'like', $term)->orWhere('reference', 'like', $term)
                ->whereNotNull('case_id')->limit(10)->get()
                ->map(fn ($s) => ['type' => 'Source Record', 'label' => $s->title, 'url' => route('portal.cases.show', $s->case_id)]));
        }

        if ($request->user()->can('leads.view')) {
            $results = $results->merge(Lead::query()
                ->where('lead_number', 'like', $term)
                ->orWhere('first_name', 'like', $term)
                ->orWhere('last_name', 'like', $term)
                ->orWhere('former_owner_name', 'like', $term)
                ->orWhere('property_address', 'like', $term)
                ->orWhere('email', 'like', $term)
                ->orWhere('phone', 'like', $term)
                ->limit(20)->get()
                ->map(fn ($l) => ['type' => 'Lead', 'label' => $l->lead_number.' — '.$l->fullName(), 'url' => route('portal.leads.show', $l)]));
        }

        return view('portal.search', ['q' => $q, 'results' => $results->values()]);
    }
}
