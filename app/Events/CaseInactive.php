<?php

namespace App\Events;

class CaseInactive extends DomainEvent
{
    public function key(): string
    {
        return 'case.inactive';
    }
}
