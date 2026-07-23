<?php

namespace App\Events;

class CaseFieldChanged extends DomainEvent
{
    public function key(): string
    {
        return 'case.field_changed';
    }
}
