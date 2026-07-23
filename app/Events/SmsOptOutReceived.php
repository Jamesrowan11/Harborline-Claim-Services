<?php

namespace App\Events;

class SmsOptOutReceived extends DomainEvent
{
    public function key(): string
    {
        return 'sms.opt_out';
    }
}
