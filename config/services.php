<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'gate_terminal' => [
        'enabled' => env('GATE_TERMINAL_MODE', false),
        'id' => env('GATE_TERMINAL_ID'),
        'token' => env('GATE_TERMINAL_TOKEN'),
        'central_url' => env('GATE_CENTRAL_URL'),
        'offline_window_minutes' => (int) env('GATE_OFFLINE_WINDOW_MINUTES', 240),
        'snapshot_batch_size' => (int) env('GATE_SNAPSHOT_BATCH_SIZE', 1000),
        'sync_batch_size' => (int) env('GATE_SYNC_BATCH_SIZE', 100),
    ],

];
