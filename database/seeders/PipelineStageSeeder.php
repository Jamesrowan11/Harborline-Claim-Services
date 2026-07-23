<?php

namespace Database\Seeders;

use App\Models\PipelineStage;
use Illuminate\Database\Seeder;

class PipelineStageSeeder extends Seeder
{
    /**
     * [key, name, client label, closed?, hold?, compliance?, attorney?]
     * Client labels use only the approved client-facing vocabulary.
     */
    public const STAGES = [
        ['new_lead', 'New Lead', 'Information Received'],
        ['preliminary_screening', 'Preliminary Screening', 'Under Preliminary Review'],
        ['duplicate_review', 'Duplicate Review', 'Under Preliminary Review'],
        ['property_identified', 'Property Identified', 'Under Preliminary Review'],
        ['source_records_located', 'Source Records Located', 'Under Preliminary Review'],
        ['possible_surplus_identified', 'Possible Surplus Identified', 'Possible Funds Being Verified'],
        ['verification_pending', 'Verification Pending', 'Possible Funds Being Verified'],
        ['surplus_verified', 'Surplus Verified', 'Possible Funds Being Verified'],
        ['funds_holder_confirmed', 'Funds Holder Confirmed', 'Possible Funds Being Verified'],
        ['already_claimed', 'Already Claimed or Distributed', 'Closed'],
        ['claimant_identification', 'Claimant Identification', 'Eligibility Review'],
        ['claimant_located', 'Claimant Located', 'Eligibility Review'],
        ['outreach_compliance_review', 'Outreach Compliance Review', 'Eligibility Review', false, false, true],
        ['outreach_approved', 'Outreach Approved', 'Eligibility Review'],
        ['contact_attempted', 'Contact Attempted', 'Eligibility Review'],
        ['interested_claimant', 'Interested Claimant', 'Eligibility Review'],
        ['identity_verification', 'Identity Verification', 'Eligibility Review', false, false, true],
        ['agreement_pending', 'Agreement Pending', 'Documents Needed', false, false, false, true],
        ['agreement_signed', 'Agreement Signed', 'Documents Needed'],
        ['documents_requested', 'Documents Requested', 'Documents Needed'],
        ['documents_collected', 'Documents Collected', 'Documents Needed'],
        ['compliance_review', 'Compliance Review', 'Professional Review', false, false, true],
        ['attorney_review_required', 'Attorney Review Required', 'Professional Review', false, false, false, true],
        ['attorney_review_completed', 'Attorney Review Completed', 'Professional Review'],
        ['claim_preparation', 'Claim Preparation', 'Claim Being Prepared'],
        ['claim_filed', 'Claim Filed', 'Claim Submitted'],
        ['awaiting_decision', 'Awaiting Court, County, Trustee, Auditor, or Funds Holder', 'Awaiting Decision'],
        ['additional_information_requested', 'Additional Information Requested', 'Additional Information Needed'],
        ['claim_approved', 'Claim Approved', 'Approved'],
        ['payment_pending', 'Payment Pending', 'Payment Being Processed'],
        ['funds_received', 'Funds Received', 'Payment Being Processed'],
        ['client_distribution_completed', 'Client Distribution Completed', 'Completed'],
        ['company_fee_recorded', 'Company Fee Recorded', 'Completed'],
        ['closed_successfully', 'Closed Successfully', 'Completed', true],
        ['closed_no_surplus', 'Closed—No Surplus', 'Closed', true],
        ['closed_previously_claimed', 'Closed—Funds Previously Claimed', 'Closed', true],
        ['closed_unable_to_locate', 'Closed—Unable to Locate Claimant', 'Closed', true],
        ['closed_claimant_declined', 'Closed—Claimant Declined', 'Closed', true],
        ['closed_claim_denied', 'Closed—Claim Denied', 'Closed', true],
        ['closed_legal_conflict', 'Closed—Legal Conflict', 'Closed', true],
        ['closed_duplicate', 'Closed—Duplicate', 'Closed', true],
        ['on_hold', 'On Hold', 'Under Preliminary Review', false, true],
    ];

    public function run(): void
    {
        foreach (self::STAGES as $i => $stage) {
            PipelineStage::query()->updateOrCreate(
                ['key' => $stage[0]],
                [
                    'name' => $stage[1],
                    'client_label' => $stage[2],
                    'sort_order' => ($i + 1) * 10,
                    'is_closed' => $stage[3] ?? false,
                    'is_hold' => $stage[4] ?? false,
                    'requires_compliance_review' => $stage[5] ?? false,
                    'requires_attorney_review' => $stage[6] ?? false,
                    'active' => true,
                ],
            );
        }
    }
}
