<?php

namespace Database\Seeders;

use App\Models\DocumentTemplate;
use Illuminate\Database\Seeder;

/**
 * Editable communication/document templates. Every template ships in DRAFT
 * status — nothing can be sent until an administrator (and, where flagged,
 * an attorney) approves it.
 */
class DocumentTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $footer = "\n\n{{brand.name}}\n{{brand.phone}}  •  {{brand.email}}\n\n{{brand.disclaimer}}";

        $templates = [
            ['inquiry_acknowledgment', 'Initial inquiry acknowledgment', 'email', false,
                'We received your inquiry — {{case.number}}',
                "Dear {{claimant.first_name}},\n\nThank you for contacting {{brand.name}}. Your inquiry has been received and is under preliminary review. Your reference number is {{case.number}}.\n\nImportant: this acknowledgment does not mean funds exist or that recovery is guaranteed. We will research public records and contact you with honest findings.".$footer],
            ['possible_funds_notice', 'Possible-funds notice', 'letter', true,
                'Possible funds that may relate to you — reference {{case.number}}',
                "Dear {{claimant.name}},\n\nOur research of public records suggests you may have a possible claim to funds remaining after a property sale in {{case.county}}, {{case.state}}. The existence, amount, and ownership of any funds must still be independently verified, and recovery is not guaranteed.\n\nYou may verify this letter is genuinely from us at any time using reference {{case.number}} on our website.\n\nThere is no cost and no obligation to speak with us.".$footer],
            ['verification_letter', 'Verification letter', 'letter', true,
                'Update on verification — {{case.number}}',
                "Dear {{claimant.name}},\n\nWe are writing with an update on the verification of possible funds connected to reference {{case.number}}. Current status: {{case.status_label}}.".$footer],
            ['follow_up_letter', 'Follow-up letter', 'letter', true,
                'Following up — {{case.number}}',
                "Dear {{claimant.name}},\n\nWe recently reached out about possible funds that may relate to you (reference {{case.number}}). If you would like to talk — or would like us to stop contacting you — either request is welcome.".$footer],
            ['document_request', 'Document request', 'email', false,
                'Documents needed — {{case.number}}',
                "Dear {{claimant.first_name}},\n\nTo move your file forward we need one or more documents from you. Please upload them through your secure portal — never by regular email.\n\nSign in at any time to see exactly what is needed.".$footer],
            ['unable_to_contact', 'Unable-to-contact letter', 'letter', true,
                'We have been unable to reach you — {{case.number}}',
                "Dear {{claimant.name}},\n\nWe have tried to reach you about reference {{case.number}} without success. If you wish to proceed, please contact us. If we do not hear from you, we will close our file without further contact.".$footer],
            ['attorney_referral', 'Attorney referral', 'letter', true,
                'Referral to independent counsel — {{case.number}}',
                "Dear {{claimant.name}},\n\nYour matter involves legal steps that must be handled by a licensed attorney. With your consent, we will refer your file to independent counsel. You are always free to choose your own attorney instead.".$footer],
            ['claim_status_update', 'Claim status update', 'email', false,
                'Status update — {{case.number}}',
                "Dear {{claimant.first_name}},\n\nYour case status is now: {{case.status_label}}.\n\nSign in to your secure portal for details. We never include sensitive case information in email.".$footer],
            ['additional_information_request', 'Additional-information request', 'email', false,
                'A little more information is needed — {{case.number}}',
                "Dear {{claimant.first_name}},\n\nThe funds holder reviewing the claim has asked for additional information. Please sign in to your portal to see the request and respond.".$footer],
            ['case_completion', 'Case completion letter', 'letter', true,
                'Your case is complete — {{case.number}}',
                "Dear {{claimant.name}},\n\nWe are pleased to confirm that your case (reference {{case.number}}) is complete. A full summary of amounts received and distributed is available in your portal and enclosed with this letter.\n\nThank you for trusting us with your matter.".$footer],
            ['case_closure', 'Case closure letter', 'letter', true,
                'Closing our file — {{case.number}}',
                "Dear {{claimant.name}},\n\nWe are closing our file on reference {{case.number}}. This letter explains why, and what options—if any—remain available to you.".$footer],
            ['internal_research_sheet', 'Internal research sheet', 'internal', false,
                'Research sheet — {{case.number}}',
                "CASE: {{case.number}}\nCOUNTY: {{case.county}}, {{case.state}}\nMANAGER: {{case.manager}}\nDATE: {{today}}\n\nSALE DETAILS:\n\nSOURCE RECORDS REVIEWED:\n\nPOSSIBLE SURPLUS:\n\nFUNDS HOLDER:\n\nNEXT STEPS:"],
            ['attorney_case_summary', 'Attorney case summary', 'internal', false,
                'Case summary for counsel — {{case.number}}',
                "CASE: {{case.number}} ({{case.county}}, {{case.state}})\nSTATUS: {{case.status_label}}\nPREPARED: {{today}}\n\nCLAIMANT: {{claimant.name}}\n\nFACTS:\n\nOPEN LEGAL QUESTIONS (for counsel):\n\nDOCUMENTS ATTACHED:"],
            ['client_payment_summary', 'Client payment summary', 'letter', true,
                'Payment summary — {{case.number}}',
                "Dear {{claimant.name}},\n\nThis summary sets out, line by line, the funds received by the funds holder decision, any agreed fees, and the amount distributed to you for reference {{case.number}}.".$footer],
        ];

        foreach ($templates as [$key, $name, $category, $attorneyReview, $subject, $body]) {
            DocumentTemplate::query()->updateOrCreate(['key' => $key], [
                'name' => $name,
                'category' => $category,
                'subject' => $subject,
                'body' => $body,
                'approval_status' => 'draft', // nothing sends until approved
                'requires_attorney_review' => $attorneyReview,
                'active' => true,
            ]);
        }
    }
}
