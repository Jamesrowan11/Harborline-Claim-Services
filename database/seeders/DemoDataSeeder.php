<?php

namespace Database\Seeders;

use App\Models\Agreement;
use App\Models\CaseFile;
use App\Models\CaseTask;
use App\Models\Claim;
use App\Models\Claimant;
use App\Models\Communication;
use App\Models\CourtCase;
use App\Models\Deadline;
use App\Models\DocketEntry;
use App\Models\DocumentRequest;
use App\Models\FundsHolder;
use App\Models\Lead;
use App\Models\Payment;
use App\Models\PipelineStage;
use App\Models\Property;
use App\Models\Sale;
use App\Models\SourceRecord;
use App\Models\SurplusRecord;
use App\Models\User;
use App\Services\CaseNumberService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * FICTIONAL DEMONSTRATION DATA ONLY.
 * Every person, property, sale, court case, and amount below is invented.
 * Names are deliberately implausible ("Demoperson", "Sampleheir") and all
 * records carry a "[DEMO]" marker so they can never be mistaken for real
 * former property owners.
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->firstOrCreate(['email' => 'admin@example.test'], [
            'name' => 'Demo Administrator',
            'password' => Hash::make('demo-admin-password-123'),
            'user_type' => 'staff',
            'title' => 'Administrator',
        ]);
        $admin->syncRoles(['Super Administrator']);

        $manager = User::query()->firstOrCreate(['email' => 'manager@example.test'], [
            'name' => 'Casey Demomanager',
            'password' => Hash::make('demo-manager-password-123'),
            'user_type' => 'staff',
            'title' => 'Case Manager',
        ]);
        $manager->syncRoles(['Case Manager']);

        $researcher = User::query()->firstOrCreate(['email' => 'researcher@example.test'], [
            'name' => 'Riley Demoresearcher',
            'password' => Hash::make('demo-researcher-password-123'),
            'user_type' => 'staff',
            'title' => 'Researcher',
        ]);
        $researcher->syncRoles(['Researcher']);

        // Fictional funds holders
        $court = FundsHolder::query()->firstOrCreate(
            ['name' => '[DEMO] Circuit Court for Sample County'],
            ['holder_type' => 'court', 'claim_procedure_notes' => 'Fictional demo record.'],
        );

        $stages = PipelineStage::query()->pluck('id', 'key');
        $caseNumbers = app(CaseNumberService::class);

        // Demo leads
        $leadRows = [
            ['Dana', 'Demoperson', 'screening', 'Anne Arundel'],
            ['Morgan', 'Sampleheir', 'new', 'Howard'],
            ['Jordan', 'Testclaimant', 'new', 'Baltimore'],
        ];
        foreach ($leadRows as $i => [$first, $last, $status, $county]) {
            Lead::query()->firstOrCreate(['lead_number' => 'L-2026-90000'.($i + 1)], [
                'status' => $status,
                'first_name' => $first,
                'last_name' => $last,
                'former_owner_name' => "$first $last",
                'relationship_to_owner' => 'I am the former owner',
                'property_address' => '[DEMO] '.(100 + $i).' Fictional Harbor Lane',
                'property_county' => $county,
                'property_state' => 'MD',
                'email' => strtolower($first).'@example.test',
                'phone' => '555-0100',
                'consent_to_contact' => true,
                'privacy_policy_agreed' => true,
                'electronic_consent' => true,
                'source' => 'demo_seed',
                'assigned_to' => $researcher->id,
            ]);
        }

        // Demo cases at interesting pipeline points
        $demoCases = [
            ['possible_surplus_identified', 'Anne Arundel', 'AA', 42500.00, null],
            ['documents_requested', 'Howard', 'HO', 61200.00, 58900.00],
            ['claim_filed', 'Baltimore', 'BA', 19800.00, 19800.00],
            ['closed_successfully', 'Anne Arundel', 'AA', 33000.00, 33000.00],
        ];

        foreach ($demoCases as $i => [$stageKey, $county, $countyCode, $estimated, $verified]) {
            $property = Property::query()->firstOrCreate(
                ['address_line1' => '[DEMO] '.(200 + $i).' Imaginary Shore Drive'],
                [
                    'city' => 'Sampletown', 'county' => $county, 'county_code' => $countyCode,
                    'state' => 'MD', 'zip' => '21'.(400 + $i),
                    'parcel_number' => 'DEMO-PARCEL-'.(1000 + $i),
                    'former_owner_names' => ['[DEMO] Pat Demoperson'],
                ],
            );

            $sale = Sale::query()->firstOrCreate(
                ['property_id' => $property->id],
                ['sale_type' => 'foreclosure', 'sale_date' => now()->subMonths(8 + $i), 'sale_price' => 250000 + $i * 10000],
            );

            $courtCase = CourtCase::query()->firstOrCreate(
                ['court_case_number' => 'DEMO-CV-2025-00'.(10 + $i)],
                ['court_name' => '[DEMO] Circuit Court for '.$county.' County', 'county' => $county, 'state' => 'MD', 'property_id' => $property->id],
            );
            DocketEntry::query()->firstOrCreate(
                ['court_case_id' => $courtCase->id, 'title' => '[DEMO] Report of sale filed'],
                ['entry_date' => now()->subMonths(7 + $i)],
            );

            $case = CaseFile::query()->firstOrCreate(
                ['lead_id' => null, 'property_id' => $property->id],
                [
                    'case_number' => $caseNumbers->next('MD', $countyCode),
                    'pipeline_stage_id' => $stages[$stageKey],
                    'sale_id' => $sale->id,
                    'court_case_id' => $courtCase->id,
                    'funds_holder_id' => $court->id,
                    'county' => $county, 'county_code' => $countyCode, 'state' => 'MD',
                    'estimated_surplus' => $estimated,
                    'verified_surplus' => $verified,
                    'verification_level' => $verified ? 'holder_confirmed' : 'preliminary',
                    'assigned_to' => $researcher->id,
                    'case_manager_id' => $manager->id,
                    'outreach_approved' => $verified !== null,
                    'last_activity_at' => now()->subDays($i * 3),
                    'closed_at' => $stageKey === 'closed_successfully' ? now()->subDays(10) : null,
                ],
            );

            $claimant = Claimant::query()->firstOrCreate(
                ['first_name' => 'Pat'.$i, 'last_name' => 'Demoperson'],
                ['type' => 'individual', 'relationship_to_owner' => 'I am the former owner'],
            );
            $case->claimants()->syncWithoutDetaching([$claimant->id => ['role' => 'primary']]);
            $claimant->emailAddresses()->firstOrCreate(['email' => "client$i@example.test"]);

            SurplusRecord::query()->firstOrCreate(['case_id' => $case->id], [
                'sale_id' => $sale->id,
                'funds_holder_id' => $court->id,
                'estimated_amount' => $estimated,
                'verified_amount' => $verified,
                'status' => $verified ? 'verified' : 'possible',
                'verified_at' => $verified ? now()->subMonths(2) : null,
                'verification_expires_at' => $verified ? now()->addDays(30 - $i * 20) : null,
                'verified_by' => $verified ? $researcher->id : null,
            ]);

            SourceRecord::query()->firstOrCreate(
                ['case_id' => $case->id, 'title' => '[DEMO] Auditor report for sale'],
                [
                    'source_type' => 'auditor_report',
                    'reference' => 'https://example.test/demo-record',
                    'record_date' => now()->subMonths(6),
                    'retrieved_at' => now()->subMonths(1),
                    'stale_after' => now()->addDays(14),
                    'retrieved_by' => $researcher->id,
                    'summary' => 'Fictional demonstration source record.',
                ],
            );

            CaseTask::query()->firstOrCreate(
                ['case_id' => $case->id, 'title' => '[DEMO] Review next step for stage: '.$stageKey],
                ['assigned_to' => $manager->id, 'due_at' => now()->addDays(5), 'created_by' => $admin->id],
            );

            Deadline::query()->firstOrCreate(
                ['case_id' => $case->id, 'name' => '[DEMO] Follow-up date (not a legal deadline)'],
                ['due_at' => now()->addDays(20 + $i * 5), 'source' => 'internal'],
            );

            Communication::query()->firstOrCreate(
                ['case_id' => $case->id, 'subject' => '[DEMO] Seeded outbound letter record'],
                [
                    'channel' => 'letter', 'direction' => 'outbound', 'status' => 'sent',
                    'body_rendered' => 'Fictional demonstration communication.', 'sent_at' => now()->subWeeks(2),
                    'generated_by' => $manager->id,
                ],
            );

            if (in_array($stageKey, ['claim_filed', 'closed_successfully'], true)) {
                Claim::query()->firstOrCreate(['case_id' => $case->id], [
                    'funds_holder_id' => $court->id,
                    'status' => $stageKey === 'closed_successfully' ? 'paid' : 'filed',
                    'amount_claimed' => $verified,
                    'amount_approved' => $stageKey === 'closed_successfully' ? $verified : null,
                    'filed_at' => now()->subMonths(1),
                    'filed_by' => $manager->id,
                ]);
            }

            if ($stageKey === 'documents_requested') {
                DocumentRequest::query()->firstOrCreate(
                    ['case_id' => $case->id, 'name' => '[DEMO] Government-issued photo ID (copy)'],
                    ['claimant_id' => $claimant->id, 'instructions' => 'Upload through the secure portal. Fictional demo request.', 'due_at' => now()->addDays(14), 'created_by' => $manager->id],
                );
            }

            if ($stageKey === 'closed_successfully') {
                Payment::query()->firstOrCreate(
                    ['case_id' => $case->id, 'payment_type' => 'funds_received'],
                    ['amount' => 33000, 'method' => 'check', 'occurred_on' => now()->subDays(20), 'recorded_by' => $admin->id],
                );
                Payment::query()->firstOrCreate(
                    ['case_id' => $case->id, 'payment_type' => 'client_distribution'],
                    ['amount' => 24750, 'method' => 'check', 'occurred_on' => now()->subDays(12), 'recorded_by' => $admin->id],
                );
                Payment::query()->firstOrCreate(
                    ['case_id' => $case->id, 'payment_type' => 'company_fee'],
                    ['amount' => 8250, 'method' => 'check', 'occurred_on' => now()->subDays(12), 'recorded_by' => $admin->id],
                );

                Agreement::query()->firstOrCreate(
                    ['case_id' => $case->id, 'claimant_id' => $claimant->id],
                    [
                        'title' => '[DEMO] Assistance Agreement',
                        'body_rendered' => "FICTIONAL DEMONSTRATION AGREEMENT.\n\n[ATTORNEY REVIEW REQUIRED] Real agreement text must be drafted by licensed counsel.",
                        'status' => 'signed', 'fee_percent' => 25.00,
                        'sent_at' => now()->subMonths(3), 'signed_at' => now()->subMonths(3),
                        'signature_name' => 'Pat3 Demoperson', 'approved_by' => $admin->id,
                    ],
                );

                // Client portal login for the completed demo case
                $clientUser = User::query()->firstOrCreate(['email' => 'client@example.test'], [
                    'name' => 'Pat3 Demoperson',
                    'password' => Hash::make('demo-client-password-123'),
                    'user_type' => 'client',
                    'claimant_id' => $claimant->id,
                ]);
                $clientUser->syncRoles(['Client']);
                if (! $clientUser->claimant_id) {
                    $clientUser->update(['claimant_id' => $claimant->id]);
                }
            }
        }
    }
}
