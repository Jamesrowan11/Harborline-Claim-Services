<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ClientPortalNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $message, public ?string $caseNumber = null)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Update on your case with :brand', ['brand' => \App\Services\Settings::brand('name')]))
            ->line($this->message)
            ->line(__('Sign in to your secure portal to see details. We never include sensitive case details in email.'))
            ->action(__('Open Client Portal'), route('login'));
    }

    public function toArray(object $notifiable): array
    {
        return ['message' => $this->message, 'case_number' => $this->caseNumber];
    }
}
