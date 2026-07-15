<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
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

    'discord' => [
        // Adres webhooka kanału, na który lecą alerty o błędach.
        'webhook' => env('DISCORD_WEBHOOK_URL'),
    ],

    'deepseek' => [
        // Redakcja/generowanie treści („Popraw przez AI"). Klucz tylko po stronie serwera.
        'key' => env('DEEPSEEK_API_KEY'),
        'base_url' => env('DEEPSEEK_BASE_URL', 'https://api.deepseek.com'),
        'model' => env('DEEPSEEK_MODEL', 'deepseek-chat'),
    ],

    'mf' => [
        // Biała lista podatników VAT (Ministerstwo Finansów) — auto-fill firmy z NIP. Bez klucza.
        'base_url' => env('MF_WHITELIST_URL', 'https://wl-api.mf.gov.pl'),
    ],

    'gus' => [
        // GUS REGON BIR1.1 — dokładny auto-fill firmy z NIP (pełna nazwa + adres + województwo).
        // Wymaga darmowego klucza użytkownika BIR. Pusty klucz = pomijamy GUS (fallback: Biała lista).
        'key' => env('GUS_REGON_KEY'),
        'base_url' => env('GUS_REGON_URL', 'https://wyszukiwarkaregon.stat.gov.pl'),
    ],

];
