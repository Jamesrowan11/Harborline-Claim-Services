<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\AuditEvent;
use App\Models\ConsentRecord;
use App\Models\OptOut;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function edit(Request $request)
    {
        $claimant = $request->user()->claimant?->load(['emailAddresses', 'phoneNumbers', 'addresses']);

        return view('client.profile', ['claimant' => $claimant]);
    }

    public function update(Request $request)
    {
        $claimant = $request->user()->claimant;
        abort_unless($claimant, 404);

        $data = $request->validate([
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:190'],
            'address_line1' => ['nullable', 'string', 'max:200'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'size:2'],
            'zip' => ['nullable', 'string', 'max:10'],
            'preferred_contact_method' => ['nullable', 'in:email,phone,mail'],
        ]);

        if (filled($data['email'] ?? null)) {
            $claimant->emailAddresses()->updateOrCreate(['label' => 'primary'], ['email' => $data['email']]);
        }
        if (filled($data['phone'] ?? null)) {
            $claimant->phoneNumbers()->updateOrCreate(['label' => 'mobile'], ['number' => $data['phone']]);
        }
        if (filled($data['address_line1'] ?? null)) {
            $claimant->addresses()->updateOrCreate(['label' => 'mailing'], [
                'line1' => $data['address_line1'],
                'city' => $data['city'] ?? '',
                'state' => strtoupper($data['state'] ?? ''),
                'zip' => $data['zip'] ?? '',
            ]);
        }

        return back()->with('status', __('Your contact details have been updated.'));
    }

    public function withdrawSmsConsent(Request $request)
    {
        $claimant = $request->user()->claimant;
        abort_unless($claimant, 404);

        ConsentRecord::query()->create([
            'consentable_type' => $claimant->getMorphClass(),
            'consentable_id' => $claimant->id,
            'consent_type' => 'sms',
            'granted' => false,
            'source' => 'portal',
            'ip_address' => $request->ip(),
            'occurred_at' => now(),
        ]);

        foreach ($claimant->phoneNumbers as $phone) {
            OptOut::query()->firstOrCreate([
                'channel' => 'sms',
                'value' => $phone->number,
                'optoutable_type' => $claimant->getMorphClass(),
                'optoutable_id' => $claimant->id,
            ], ['reason' => 'Withdrawn via client portal', 'occurred_at' => now()]);
        }

        event(new \App\Events\SmsOptOutReceived(null, null, ['claimant_id' => $claimant->id]));

        return back()->with('status', __('You will no longer receive text messages from us.'));
    }

    public function requestStopContact(Request $request)
    {
        $claimant = $request->user()->claimant;
        abort_unless($claimant, 404);

        foreach (['email', 'sms', 'phone', 'mail'] as $channel) {
            foreach ($claimant->emailAddresses as $email) {
                OptOut::query()->firstOrCreate([
                    'channel' => $channel, 'value' => $email->email,
                    'optoutable_type' => $claimant->getMorphClass(), 'optoutable_id' => $claimant->id,
                ], ['reason' => 'Stop-contact request via portal', 'occurred_at' => now()]);
            }
            foreach ($claimant->phoneNumbers as $phone) {
                OptOut::query()->firstOrCreate([
                    'channel' => $channel, 'value' => $phone->number,
                    'optoutable_type' => $claimant->getMorphClass(), 'optoutable_id' => $claimant->id,
                ], ['reason' => 'Stop-contact request via portal', 'occurred_at' => now()]);
            }
        }

        AuditEvent::record('stop_contact_requested', $claimant);

        foreach ($claimant->cases as $case) {
            $case->update(['outreach_approved' => false]);
            $case->caseManager?->notify(new \App\Notifications\InternalAlertNotification(
                __('Client requested to stop contact on :number', ['number' => $case->case_number]), $case->case_number));
        }

        return back()->with('status', __('We have recorded your request. We will only contact you where legally required.'));
    }

    public function requestAccountClosure(Request $request)
    {
        $claimant = $request->user()->claimant;
        abort_unless($claimant, 404);

        AuditEvent::record('account_closure_requested', $claimant);

        foreach ($claimant->cases as $case) {
            $case->caseManager?->notify(new \App\Notifications\InternalAlertNotification(
                __('Client requested account closure (:number). Review retention and legal-hold rules before actioning.', ['number' => $case->case_number]),
                $case->case_number));
        }

        return back()->with('status', __('Your closure request has been recorded. A team member will confirm what is possible under our record-keeping obligations.'));
    }
}
