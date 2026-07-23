<?php

namespace App\Events;

class LeadCreated extends DomainEvent
{
    public function key(): string
    {
        return 'lead.created';
    }
}
