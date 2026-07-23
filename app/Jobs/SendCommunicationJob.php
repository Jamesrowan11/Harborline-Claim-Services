<?php

namespace App\Jobs;

use App\Models\Communication;
use App\Services\SmsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendCommunicationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [30, 120, 600];

    public function __construct(public int $communicationId)
    {
    }

    public function handle(SmsService $sms): void
    {
        $communication = Communication::query()->find($this->communicationId);

        if (! $communication || $communication->status !== 'queued') {
            return;
        }

        if (blank($communication->recipient)) {
            $communication->update(['status' => 'failed', 'meta' => ['error' => 'No recipient']]);

            return;
        }

        $ok = match ($communication->channel) {
            'email' => $this->sendEmail($communication),
            'sms' => $sms->send($communication->recipient, strip_tags((string) $communication->body_rendered)),
            default => false, // letters and calls are never auto-delivered
        };

        $communication->update([
            'status' => $ok ? 'sent' : 'failed',
            'sent_at' => $ok ? now() : null,
        ]);
    }

    private function sendEmail(Communication $communication): bool
    {
        Mail::html(nl2br(e($communication->body_rendered)), function ($message) use ($communication) {
            $message->to($communication->recipient)
                ->subject($communication->subject ?: __('A message from :brand', ['brand' => \App\Services\Settings::brand('name')]));
        });

        return true;
    }
}
