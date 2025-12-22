<?php

return [
    'email' => [
        'enabled' => env('REPORTS_EMAIL_ENABLED', false),
        'to' => env('REPORTS_EMAIL_TO', ''),
        'cc' => env('REPORTS_EMAIL_CC', ''),
        'attach_file' => env('REPORTS_EMAIL_ATTACH_FILE', true),
    ],
    'whatsapp' => [
        'enabled' => env('REPORTS_WHATSAPP_ENABLED', false),
        'to' => env('REPORTS_WHATSAPP_TO', ''),
        'provider' => env('REPORTS_WHATSAPP_PROVIDER', 'callmebot'), // twilio, callmebot, evolution

        // Twilio Configuration
        'twilio' => [
            'sid' => env('TWILIO_ACCOUNT_SID', ''),
            'token' => env('TWILIO_AUTH_TOKEN', ''),
            'from' => env('TWILIO_WHATSAPP_FROM', ''), // Formato: +14155238886
        ],

        // Callmebot Configuration (grátis)
        'callmebot' => [
            'api_key' => env('CALLMEBOT_API_KEY', ''),
        ],

        // Evolution API Configuration (self-hosted)
        'evolution' => [
            'api_url' => env('EVOLUTION_API_URL', ''),
            'api_key' => env('EVOLUTION_API_KEY', ''),
            'instance' => env('EVOLUTION_INSTANCE', ''),
        ],
    ],
];