<?php

namespace App\Events;

class DocumentRejected extends DomainEvent
{
    public function key(): string
    {
        return 'document.rejected';
    }
}
