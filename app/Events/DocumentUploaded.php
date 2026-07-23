<?php

namespace App\Events;

class DocumentUploaded extends DomainEvent
{
    public function key(): string
    {
        return 'document.uploaded';
    }
}
