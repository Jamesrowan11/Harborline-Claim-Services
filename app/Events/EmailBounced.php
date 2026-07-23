<?php

namespace App\Events;

class EmailBounced extends DomainEvent
{
    public function key(): string
    {
        return 'email.bounced';
    }
}
