<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Modular SMS delivery. The 'log' driver writes to the application log (safe
 * default). The 'twilio-compatible' driver posts to any Twilio-style API
 * (Twilio, RingCentral SMS gateways, etc.) configured via env. SMS remains
 * fully disabled unless SMS_ENABLED=true.
 * [ATTORNEY REVIEW REQUIRED] SMS outreach rules (TCPA and state equivalents)
 * must be reviewed by counsel before enabling in production.
 */
class SmsService
{
    public function send(string $to, string $body): bool
    {
        if (! config('security.sms.enabled')) {
            return false;
        }

        return match (config('security.sms.driver')) {
            'twilio-compatible' => $this->sendViaApi($to, $body),
            default => $this->sendViaLog($to, $body),
        };
    }

    private function sendViaLog(string $to, string $body): bool
    {
        Log::info('SMS (log driver)', ['to' => $to, 'body' => $body]);

        return true;
    }

    private function sendViaApi(string $to, string $body): bool
    {
        $response = Http::timeout(15)
            ->withToken((string) config('security.sms.api_key'))
            ->asForm()
            ->post((string) config('security.sms.api_url'), [
                'To' => $to,
                'From' => (string) config('security.sms.from_number'),
                'Body' => $body,
            ]);

        return $response->successful();
    }
}
