<?php

namespace App\Services;

use App\Events\CaseCreated;
use App\Models\CaseFile;
use App\Models\Claimant;
use App\Models\Lead;
use App\Models\PipelineStage;
use App\Models\Property;
use Illuminate\Support\Facades\DB;

class CaseConversionService
{
    public function __construct(private CaseNumberService $caseNumbers)
    {
    }

    /** County codes for Maryland; admins can extend via settings key 'county_codes'. */
    public const MD_COUNTY_CODES = [
        'Allegany' => 'AL', 'Anne Arundel' => 'AA', 'Baltimore City' => 'BC',
        'Baltimore' => 'BA', 'Calvert' => 'CV', 'Caroline' => 'CE', 'Carroll' => 'CR',
        'Cecil' => 'CC', 'Charles' => 'CH', 'Dorchester' => 'DO', 'Frederick' => 'FR',
        'Garrett' => 'GA', 'Harford' => 'HA', 'Howard' => 'HO', 'Kent' => 'KE',
        'Montgomery' => 'MO', "Prince George's" => 'PG', 'Queen Anne\'s' => 'QA',
        'Somerset' => 'SO', 'St. Mary\'s' => 'SM', 'Talbot' => 'TA',
        'Washington' => 'WA', 'Wicomico' => 'WI', 'Worcester' => 'WO',
    ];

    public function countyCode(?string $county, ?string $state): string
    {
        $custom = (array) Settings::get('county_codes', []);
        $map = array_merge(self::MD_COUNTY_CODES, $custom);

        foreach ($map as $name => $code) {
            if (strcasecmp($name, (string) $county) === 0) {
                return $code;
            }
        }

        return strtoupper(substr(preg_replace('/[^A-Za-z]/', '', (string) $county) ?: 'XX', 0, 2));
    }

    public function fromLead(Lead $lead, ?int $userId = null): CaseFile
    {
        return DB::transaction(function () use ($lead, $userId) {
            $state = strtoupper($lead->property_state ?: config('branding.primary_state', 'MD'));
            $countyCode = $this->countyCode($lead->property_county, $state);

            $property = null;
            if (filled($lead->property_address)) {
                $property = Property::query()->create([
                    'address_line1' => $lead->property_address,
                    'county' => $lead->property_county ?? 'Unknown',
                    'county_code' => $countyCode,
                    'state' => $state,
                    'parcel_number' => $lead->parcel_number,
                    'tax_account_number' => $lead->tax_account_number,
                    'former_owner_names' => array_filter([$lead->former_owner_name]),
                ]);
            }

            $case = CaseFile::query()->create([
                'case_number' => $this->caseNumbers->next($state, $countyCode),
                'lead_id' => $lead->id,
                'pipeline_stage_id' => PipelineStage::query()->where('key', 'preliminary_screening')->value('id')
                    ?? PipelineStage::query()->orderBy('sort_order')->value('id'),
                'property_id' => $property?->id,
                'county' => $lead->property_county,
                'county_code' => $countyCode,
                'state' => $state,
                'assigned_to' => $lead->assigned_to ?? $userId,
                'case_manager_id' => $userId,
                'last_activity_at' => now(),
            ]);

            $claimant = Claimant::query()->create([
                'type' => 'individual',
                'first_name' => $lead->first_name,
                'middle_name' => $lead->middle_name,
                'last_name' => $lead->last_name,
                'relationship_to_owner' => $lead->relationship_to_owner,
            ]);
            $case->claimants()->attach($claimant->id, ['role' => 'primary']);

            if (filled($lead->email)) {
                $claimant->emailAddresses()->create(['email' => $lead->email]);
            }
            if (filled($lead->phone)) {
                $claimant->phoneNumbers()->create(['number' => $lead->phone, 'sms_capable' => (bool) $lead->sms_consent]);
            }

            $lead->update(['status' => 'converted', 'case_id' => $case->id]);
            $lead->tasks()->whereNull('case_id')->update(['case_id' => $case->id]);

            event(new CaseCreated($case, $lead));

            return $case;
        });
    }
}
