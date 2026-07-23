<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Services\LeadIntakeService;
use Illuminate\Http\Request;

class InquiryController extends Controller
{
    public function create()
    {
        return view('site.check');
    }

    public function store(Request $request, LeadIntakeService $intake)
    {
        // NOTE deliberately absent: SSN, bank credentials, card numbers, or any
        // highly sensitive identifiers are NEVER collected on this public form.
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:80'],
            'middle_name' => ['nullable', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'former_owner_name' => ['nullable', 'string', 'max:160'],
            'relationship_to_owner' => ['required', 'string', 'max:120'],
            'former_owner_living' => ['nullable', 'boolean'],
            'property_address' => ['required', 'string', 'max:200'],
            'property_county' => ['required', 'string', 'max:80'],
            'property_state' => ['required', 'string', 'size:2'],
            'approximate_sale_date' => ['nullable', 'string', 'max:40'],
            'email' => ['required', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:30'],
            'preferred_contact_method' => ['required', 'in:email,phone,mail'],
            'court_case_number' => ['nullable', 'string', 'max:60'],
            'parcel_number' => ['nullable', 'string', 'max:60'],
            'tax_account_number' => ['nullable', 'string', 'max:60'],
            'description' => ['nullable', 'string', 'max:5000'],
            'consent_to_contact' => ['accepted'],
            'privacy_policy_agreed' => ['accepted'],
            'electronic_consent' => ['accepted'],
            'sms_consent' => ['nullable', 'boolean'], // optional and separate — never required
            'website' => ['prohibited'], // honeypot bot protection
        ]);

        unset($data['website']);
        $data['consent_to_contact'] = true;
        $data['privacy_policy_agreed'] = true;
        $data['electronic_consent'] = true;
        $data['sms_consent'] = (bool) ($data['sms_consent'] ?? false);

        $lead = $intake->create($data, $request->ip());

        return view('site.check-submitted', ['lead' => $lead]);
    }
}
