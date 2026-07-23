<?php

namespace App\Events;

class SourceRecordStale extends DomainEvent
{
    public function key(): string
    {
        return 'source.stale';
    }
}
