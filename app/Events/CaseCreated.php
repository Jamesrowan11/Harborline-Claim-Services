<?php

namespace App\Events;

class CaseCreated extends DomainEvent
{
    public function key(): string
    {
        return 'case.created';
    }
}
