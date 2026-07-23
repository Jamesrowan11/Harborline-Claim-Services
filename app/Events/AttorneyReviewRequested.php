<?php

namespace App\Events;

class AttorneyReviewRequested extends DomainEvent
{
    public function key(): string
    {
        return 'attorney.review_requested';
    }
}
