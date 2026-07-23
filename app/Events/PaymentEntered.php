<?php

namespace App\Events;

class PaymentEntered extends DomainEvent
{
    public function key(): string
    {
        return 'payment.entered';
    }
}
