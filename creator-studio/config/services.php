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

    // One-click "demo client" sign-in on the login page, for trying the client dashboard.
    // Dev and testing only: never turn it on for a public server.
    'demo_client' => [
        'enabled' => (bool) env('DEMO_CLIENT_LOGIN', false),
    ],

    // Files clients and she attach to a request (DealFiles): size cap per file, in MB.
    'client_files' => [
        'max_mb' => (int) env('CLIENT_UPLOAD_MAX_MB', 25),
    ],

    // Encrypted off-server copy of each daily backup (Backup): any disk from config/filesystems.php.
    'backup' => [
        'disk' => env('BACKUP_DISK'),
        'keep' => (int) env('BACKUP_KEEP', 30),
        'passphrase' => env('BACKUP_PASSPHRASE'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
