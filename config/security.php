<?php

return [
    'mfa_required_for_staff' => env('MFA_REQUIRED_FOR_STAFF', true),
    'login_alerts_enabled' => env('LOGIN_ALERTS_ENABLED', true),
    'automation_global_stop' => env('AUTOMATION_GLOBAL_STOP', false),
    'session_inactivity_minutes' => 30,
    'max_upload_mb' => 20,
    'allowed_upload_mimes' => [
        'application/pdf', 'image/jpeg', 'image/png', 'image/heic',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ],
    'virus_scan' => [
        'enabled' => env('VIRUS_SCAN_ENABLED', false),
        'command' => env('VIRUS_SCAN_COMMAND', 'clamdscan --no-summary'),
    ],
    'captcha' => [
        'enabled' => env('CAPTCHA_ENABLED', true),
        'driver' => env('CAPTCHA_DRIVER', 'honeypot'),
        'site_key' => env('CAPTCHA_SITE_KEY'),
        'secret_key' => env('CAPTCHA_SECRET_KEY'),
    ],
    'sms' => [
        'enabled' => env('SMS_ENABLED', false),
        'driver' => env('SMS_DRIVER', 'log'),
        'api_url' => env('SMS_API_URL'),
        'api_key' => env('SMS_API_KEY'),
        'from_number' => env('SMS_FROM_NUMBER'),
    ],
];
