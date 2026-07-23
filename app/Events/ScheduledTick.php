<?php

namespace App\Events;

class ScheduledTick extends DomainEvent
{
    public function key(): string
    {
        return 'schedule.tick';
    }
}
