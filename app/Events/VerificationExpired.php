<?php

namespace App\Events;

class VerificationExpired extends DomainEvent
{
    public function key(): string
    {
        return 'verification.expired';
    }
}
