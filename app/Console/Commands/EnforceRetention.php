<?php

namespace App\Console\Commands;

use App\Models\AuditEvent;
use App\Models\CaseFile;
use App\Models\Communication;
use App\Models\Document;
use App\Models\ExportLog;
use App\Models\Lead;
use App\Models\LegalHold;
use App\Models\RetentionPolicy;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Applies administrator-defined retention policies. Hard rules:
 *  - inactive policies do nothing;
 *  - records under an unreleased legal hold are NEVER touched;
 *  - "review" only flags via audit note; only "delete" removes data.
 */
class EnforceRetention extends Command
{
    protected $signature = 'hcs:enforce-retention {--dry-run : Report what would happen without changing anything}';
    protected $description = 'Apply active data-retention policies (never touches records under legal hold)';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        foreach (RetentionPolicy::query()->where('active', true)->whereNotNull('retain_months')->get() as $policy) {
            $cutoff = now()->subMonths($policy->retain_months);

            $candidates = match ($policy->record_type) {
                'leads' => Lead::query()->where('status', 'closed')->where('updated_at', '<', $cutoff)->get(),
                'closed_cases' => CaseFile::query()->whereNotNull('closed_at')->where('closed_at', '<', $cutoff)->get(),
                'documents' => Document::query()->where('created_at', '<', $cutoff)->get(),
                'communications' => Communication::query()->where('created_at', '<', $cutoff)->get(),
                'exports' => ExportLog::query()->where('created_at', '<', $cutoff)->get(),
                default => collect(),
            };

            foreach ($candidates as $record) {
                if ($this->underLegalHold($record)) {
                    continue;
                }

                $label = class_basename($record).' #'.$record->getKey();

                if ($dryRun) {
                    $this->line("[dry-run] {$policy->action_after}: $label");
                    continue;
                }

                match ($policy->action_after) {
                    'review' => AuditEvent::record('retention_review_flagged', $record),
                    'anonymize' => $this->anonymize($record),
                    'delete' => $this->delete($record),
                };
            }
        }

        $this->info('Retention enforcement complete.');

        return self::SUCCESS;
    }

    private function underLegalHold($record): bool
    {
        if (($record->legal_hold ?? false) === true) {
            return true;
        }

        return LegalHold::query()
            ->where('holdable_type', $record->getMorphClass())
            ->where('holdable_id', $record->getKey())
            ->whereNull('released_at')
            ->exists();
    }

    private function anonymize($record): void
    {
        if ($record instanceof Lead) {
            $record->update([
                'first_name' => 'Redacted', 'middle_name' => null, 'last_name' => 'Redacted',
                'email' => null, 'phone' => null, 'description' => null,
            ]);
        }
        AuditEvent::record('retention_anonymized', $record);
    }

    private function delete($record): void
    {
        if ($record instanceof Document) {
            Storage::disk($record->disk)->delete($record->path);
        }
        AuditEvent::record('retention_deleted', $record, [], ['type' => class_basename($record), 'id' => $record->getKey()]);
        $record->delete();
    }
}
