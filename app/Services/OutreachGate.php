<?php

namespace App\Services;

use App\Models\CaseFile;
use App\Models\Claimant;
use App\Models\Communication;
use App\Models\DocumentTemplate;
use App\Models\Lead;
use App\Models\OptOut;

/**
 * Central gate for ALL automated outbound communication. Every rule the
 * compliance spec requires is enforced here, in one place:
 *   1. the template must be approved,
 *   2. the recipient must not have opted out,
 *   3. the channel must be legally permitted (SMS requires separate consent),
 *   4. the case must meet the required verification level,
 *   5. contact-frequency limits must be satisfied,
 *   6. the case must not be on legal or compliance hold.
 * Returns true, or a human-readable reason for the block.
 */
class OutreachGate
{
    public function check(string $channel, ?DocumentTemplate $template, ?CaseFile $case, ?Lead $lead, ?Claimant $claimant): true|string
    {
        if (! $template) {
            return 'No communication template specified.';
        }
        if (! $template->isApproved()) {
            return "Template '{$template->key}' is not approved.";
        }

        if ($case && ($case->legal_hold || $case->compliance_hold)) {
            return 'Case is on legal or compliance hold.';
        }

        if ($channel === 'sms') {
            if (! config('security.sms.enabled')) {
                return 'SMS sending is disabled in configuration.';
            }
            $smsConsent = $lead?->sms_consent
                ?? $claimant?->consentRecords()->where('consent_type', 'sms')->where('granted', true)->exists();
            if (! $smsConsent) {
                return 'Recipient has not given optional SMS consent.';
            }
        }

        $recipientEmail = $claimant?->emailAddresses()->value('email') ?? $lead?->email;
        $recipientPhone = $claimant?->phoneNumbers()->value('number') ?? $lead?->phone;
        $value = $channel === 'sms' ? $recipientPhone : $recipientEmail;

        if ($value === null || $value === '') {
            return 'No recipient address on file.';
        }
        if (OptOut::exists_for($channel, $value)) {
            return 'Recipient has opted out of this channel.';
        }

        if ($lead && ! $lead->consent_to_contact) {
            return 'Lead has not consented to contact.';
        }

        if ($case) {
            $requiredLevel = (int) Settings::get('outreach_min_verification_level', 1);
            $levels = ['none' => 0, 'preliminary' => 1, 'source_confirmed' => 2, 'holder_confirmed' => 3];
            if (($levels[$case->verification_level] ?? 0) < $requiredLevel) {
                return 'Case has not reached the required verification level for outreach.';
            }
            if (! $case->outreach_approved) {
                return 'Outreach has not been approved for this case.';
            }
        }

        $maxPerWeek = (int) Settings::get('outreach_max_contacts_per_week', 2);
        $recent = Communication::query()
            ->where('direction', 'outbound')
            ->where('recipient', $value)
            ->whereIn('status', ['queued', 'sent', 'delivered'])
            ->where('created_at', '>=', now()->subWeek())
            ->count();
        if ($recent >= $maxPerWeek) {
            return 'Contact-frequency limit reached for this recipient.';
        }

        return true;
    }
}
