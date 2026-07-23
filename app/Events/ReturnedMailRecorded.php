<?php

namespace App\Events;

class ReturnedMailRecorded extends DomainEvent
{
    public function key(): string
    {
        return 'mail.returned';
    }
}
