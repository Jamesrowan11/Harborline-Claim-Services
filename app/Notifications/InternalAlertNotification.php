<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InternalAlertNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $message, public ?string $reference = null)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(trim(($this->reference ? "[{$this->reference}] " : '').__('Portal notification')))
            ->line($this->message)
            ->line($this->reference ? __('Reference: :ref', ['ref' => $this->reference]) : '');
    }

    public function toArray(object $notifiable): array
    {
        return ['message' => $this->message, 'reference' => $this->reference];
    }
}
