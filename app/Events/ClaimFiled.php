<?php

namespace App\Events;

class ClaimFiled extends DomainEvent
{
    public function key(): string
    {
        return 'claim.filed';
    }
}
