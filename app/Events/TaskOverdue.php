<?php

namespace App\Events;

class TaskOverdue extends DomainEvent
{
    public function key(): string
    {
        return 'task.overdue';
    }
}
