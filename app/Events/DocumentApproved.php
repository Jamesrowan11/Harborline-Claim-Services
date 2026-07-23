<?php

namespace App\Events;

class DocumentApproved extends DomainEvent
{
    public function key(): string
    {
        return 'document.approved';
    }
}
