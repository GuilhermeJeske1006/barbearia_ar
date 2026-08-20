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

    'mercadopago' => [
        'client_id' => env('MP_CLIENT_ID'),
        'client_secret' => env('MP_CLIENT_SECRET'),
        'webhook_secret' => env('MP_WEBHOOK_SECRET'),
        // Token da própria aplicação da plataforma (dono do Marketplace) —
        // usado para consultar pagamentos via API a partir do webhook, antes
        // de sabermos a qual barbearia o pagamento pertence.
        'access_token' => env('MP_ACCESS_TOKEN'),
        // Fração retida pela plataforma como taxa de uso do SaaS (0.05 = 5%).
        'taxa_plataforma' => env('MP_TAXA_PLATAFORMA', 0),
        // true só em dev/staging pra testar com credenciais de teste da MP —
        // manda o checkout pro 'sandbox_init_point' em vez do 'init_point'
        // de produção. Nunca true em produção: barbearias reais usam token
        // de produção emitido pelo OAuth Connect de verdade.
        'sandbox' => env('MP_SANDBOX', false),
    ],

];
