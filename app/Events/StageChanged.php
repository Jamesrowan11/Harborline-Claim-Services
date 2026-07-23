<?php

namespace App\Events;

class StageChanged extends DomainEvent
{
    public function key(): string
    {
        return 'case.stage_changed';
    }
}
