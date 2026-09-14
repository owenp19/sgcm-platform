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

    // Asistente virtual "Miss Lorna" (Gemini)
    'gemini' => [
        'key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-3.6-flash'),
        'embed_model' => env('GEMINI_EMBED_MODEL', 'gemini-embedding-2'),
      ],

    // URL del frontend Ionic (links de QR, pagos sandbox, reset de contraseña)
    'frontend_url' => env('FRONTEND_URL', 'http://localhost:8100'),

    // Pasarela de pagos (sandbox) para tarjeta de turismo
    'payments' => [
        'environment' => env('PAYMENT_ENVIRONMENT', 'sandbox'),
        'provider' => env('PAYMENT_PROVIDER', 'sandbox'),
        'gateway_url' => env('PAYMENT_GATEWAY_URL'),
        'webhook_secret' => env('PAYMENT_WEBHOOK_SECRET', 'sandbox-webhook-secret-change-me'),
        'verify_signature' => env('PAYMENT_WEBHOOK_VERIFY', true),
    ],

];
