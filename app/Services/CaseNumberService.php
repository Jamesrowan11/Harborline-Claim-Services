<?php

namespace App\Services;

use App\Models\CaseNumberSequence;
use Illuminate\Support\Facades\DB;

/**
 * Generates case numbers from an administrator-editable format string.
 * Default: {PREFIX}-{YEAR}-{STATE}-{COUNTY}-{SEQ:6} → HCS-2026-MD-AA-000001
 * Sequences are per year/state/county and allocated under a row lock so
 * concurrent case creation never produces duplicates.
 */
class CaseNumberService
{
    public function next(string $state, string $countyCode, ?int $year = null): string
    {
        $year = $year ?? (int) now()->format('Y');
        $state = strtoupper($state ?: 'XX');
        $countyCode = strtoupper($countyCode ?: 'XX');

        $sequence = DB::transaction(function () use ($year, $state, $countyCode) {
            $row = CaseNumberSequence::query()
                ->where(['year' => $year, 'state' => $state, 'county_code' => $countyCode])
                ->lockForUpdate()
                ->first();

            if (! $row) {
                $row = CaseNumberSequence::query()->create([
                    'year' => $year, 'state' => $state, 'county_code' => $countyCode, 'last_number' => 0,
                ]);
                $row = CaseNumberSequence::query()->whereKey($row->id)->lockForUpdate()->first();
            }

            $row->last_number += 1;
            $row->save();

            return $row->last_number;
        });

        return $this->format($sequence, $year, $state, $countyCode);
    }

    public function format(int $sequence, int $year, string $state, string $countyCode): string
    {
        $format = Settings::get('case_number_format', config('branding.case_number_format'));
        $prefix = Settings::brand('case_prefix', config('branding.case_prefix'));

        return preg_replace_callback('/\{(PREFIX|YEAR|STATE|COUNTY|SEQ(?::(\d+))?)\}/', function ($m) use ($prefix, $year, $state, $countyCode, $sequence) {
            return match (true) {
                $m[1] === 'PREFIX' => $prefix,
                $m[1] === 'YEAR' => (string) $year,
                $m[1] === 'STATE' => $state,
                $m[1] === 'COUNTY' => $countyCode,
                str_starts_with($m[1], 'SEQ') => str_pad((string) $sequence, (int) ($m[2] ?? 6), '0', STR_PAD_LEFT),
                default => $m[0],
            };
        }, $format);
    }
}
