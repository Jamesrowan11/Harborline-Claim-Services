<?php

namespace App\Notifications;

use App\Models\Lead;
use App\Services\Settings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InquiryReceivedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Lead $lead)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $brand = Settings::brand('name');

        return (new MailMessage)
            ->subject(__('We received your inquiry — :number', ['number' => $this->lead->lead_number]))
            ->greeting(__('Hello :name,', ['name' => $this->lead->first_name]))
            ->line(__('Thank you for contacting :brand. Your inquiry has been submitted for preliminary review.', ['brand' => $brand]))
            ->line(__('Your reference number is :number. Please keep it for your records.', ['number' => $this->lead->lead_number]))
            ->line(__('Important: this confirmation does not mean that funds exist or that any recovery is guaranteed. We research public records first and will only contact you about verified findings.'))
            ->line(config('branding.disclaimer'))
            ->salutation(__('— The :brand team', ['brand' => $brand]));
    }
}
