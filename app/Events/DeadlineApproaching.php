<?php

namespace App\Events;

class DeadlineApproaching extends DomainEvent
{
    public function key(): string
    {
        return 'deadline.approaching';
    }
}
