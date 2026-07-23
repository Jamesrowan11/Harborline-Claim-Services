<?php

namespace App\Events;

use App\Models\CaseFile;
use App\Models\Lead;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Base class for all automation-relevant domain events. Each event carries a
 * dot-notation key (e.g. "case.stage_changed") matched against
 * Automation::trigger_event, plus context for condition evaluation.
 */
abstract class DomainEvent
{
    use Dispatchable;

    public function __construct(
        public readonly ?CaseFile $case = null,
        public readonly ?Lead $lead = null,
        public readonly array $context = [],
    ) {
    }

    abstract public function key(): string;
}
