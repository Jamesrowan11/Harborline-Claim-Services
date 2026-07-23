<?php

namespace App\Services;

use App\Models\CaseFile;
use App\Models\Lead;
use Illuminate\Support\Collection;

/**
 * Finds likely duplicates across property address, parcel number, court case
 * number, owner name, phone, email, and auction identifier.
 */
class DuplicateDetectionService
{
    public function forLead(Lead $lead): Collection
    {
        $matches = collect();

        $leadQuery = Lead::query()->whereKeyNot($lead->id)->whereNull('duplicate_of_lead_id');

        $normalized = $this->normalizeAddress($lead->property_address);
        if ($normalized) {
            $matches = $matches->merge(
                (clone $leadQuery)->get()->filter(
                    fn ($other) => $this->normalizeAddress($other->property_address) === $normalized
                )->map(fn ($l) => ['type' => 'lead', 'record' => $l, 'reason' => 'Same property address'])
            );
        }

        foreach ([
            ['parcel_number', 'Same parcel number'],
            ['court_case_number', 'Same court case number'],
            ['email', 'Same email'],
            ['phone', 'Same phone'],
        ] as [$field, $reason]) {
            if (filled($lead->$field)) {
                $matches = $matches->merge(
                    (clone $leadQuery)->where($field, $lead->$field)->get()
                        ->map(fn ($l) => ['type' => 'lead', 'record' => $l, 'reason' => $reason])
                );
            }
        }

        $caseQuery = CaseFile::query()->with('property');
        if (filled($lead->parcel_number)) {
            $matches = $matches->merge(
                $caseQuery->whereHas('property', fn ($q) => $q->where('parcel_number', $lead->parcel_number))->get()
                    ->map(fn ($c) => ['type' => 'case', 'record' => $c, 'reason' => 'Case with same parcel number'])
            );
        }
        if ($normalized) {
            $matches = $matches->merge(
                CaseFile::query()->with('property')->get()
                    ->filter(fn ($c) => $c->property && $this->normalizeAddress($c->property->address_line1) === $normalized)
                    ->map(fn ($c) => ['type' => 'case', 'record' => $c, 'reason' => 'Case with same property address'])
            );
        }

        return $matches->unique(fn ($m) => $m['type'].'-'.$m['record']->id)->values();
    }

    public function normalizeAddress(?string $address): ?string
    {
        if (blank($address)) {
            return null;
        }
        $address = strtolower(trim($address));
        $replacements = [
            'street' => 'st', 'avenue' => 'ave', 'drive' => 'dr', 'road' => 'rd',
            'court' => 'ct', 'lane' => 'ln', 'boulevard' => 'blvd', 'place' => 'pl',
            'circle' => 'cir', 'terrace' => 'ter', 'north' => 'n', 'south' => 's',
            'east' => 'e', 'west' => 'w',
        ];
        foreach ($replacements as $long => $short) {
            $address = preg_replace('/\b'.$long.'\b/', $short, $address);
        }

        return preg_replace('/[^a-z0-9]/', '', $address);
    }
}
