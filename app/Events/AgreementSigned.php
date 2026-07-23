<?php

namespace App\Events;

class AgreementSigned extends DomainEvent
{
    public function key(): string
    {
        return 'agreement.signed';
    }
}
