<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LoginAlertNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $ip)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('New sign-in to your :app account', ['app' => config('branding.name')]))
            ->line(__('Your account was just signed in from IP address :ip.', ['ip' => $this->ip]))
            ->line(__('If this was you, no action is needed. If you do not recognize this sign-in, reset your password immediately and contact your administrator.'));
    }
}
