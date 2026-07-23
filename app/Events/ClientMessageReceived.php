<?php

namespace App\Events;

class ClientMessageReceived extends DomainEvent
{
    public function key(): string
    {
        return 'client.message_received';
    }
}
