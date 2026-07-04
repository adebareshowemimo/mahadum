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

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
    ],

    'paystack' => [
        'secret' => env('PAYSTACK_SECRET'),
        'base_url' => env('PAYSTACK_BASE_URL', 'https://api.paystack.co'),
    ],

    'flutterwave' => [
        'secret' => env('FLUTTERWAVE_SECRET'),
        'secret_hash' => env('FLUTTERWAVE_SECRET_HASH'),
        'base_url' => env('FLUTTERWAVE_BASE_URL', 'https://api.flutterwave.com/v3'),
    ],

    'monnify' => [
        // Two-step auth: Basic api_key:secret → bearer token → init-transaction.
        // The same secret verifies inbound webhooks (monnify-signature, HMAC-SHA512).
        'api_key' => env('MONNIFY_API_KEY'),
        'secret' => env('MONNIFY_SECRET'),
        'contract_code' => env('MONNIFY_CONTRACT_CODE'),
        // Sandbox by default; live is https://api.monnify.com.
        'base_url' => env('MONNIFY_BASE_URL', 'https://sandbox.monnify.com'),
    ],

    'telco' => [
        // Shared secret for HMAC-SHA256 verification of inbound SDP DLR webhooks.
        'webhook_secret' => env('TELCO_WEBHOOK_SECRET'),
        // Outbound SDP calls (charge + OTP SMS) are OFF unless explicitly enabled,
        // so local/CI never hit a live operator (TelcoGatewayManager → NullTelcoGateway).
        'live' => (bool) env('TELCO_SDP_LIVE', false),
        'base_url' => env('TELCO_SDP_BASE_URL'),
        'token' => env('TELCO_SDP_TOKEN'),
    ],

    'payments' => [
        // Outbound gateway calls are OFF unless explicitly enabled, so local/CI
        // never hit a live gateway (PaymentGatewayManager → NullGateway).
        'live' => (bool) env('PAYMENT_GATEWAY_LIVE', false),
        'default' => env('PAYMENT_DEFAULT_GATEWAY', 'monnify'),
    ],

    'messaging' => [
        // Outbound SMS/WhatsApp/push are OFF unless enabled (→ NullMessagingGateway).
        'live' => (bool) env('MESSAGING_LIVE', false),
        // Which text channel a notification uses: sms | whatsapp | none.
        'text_channel' => env('MESSAGING_TEXT_CHANNEL', 'sms'),
        'sms' => [
            'base_url' => env('SMS_BASE_URL'),
            'token' => env('SMS_TOKEN'),
            'sender' => env('SMS_SENDER', 'Mahadum360'),
        ],
        'whatsapp' => [
            'base_url' => env('WHATSAPP_BASE_URL'),
            'token' => env('WHATSAPP_TOKEN'),
        ],
        'push' => [
            'fcm_url' => env('FCM_URL', 'https://fcm.googleapis.com/fcm/send'),
            'key' => env('FCM_SERVER_KEY'),
        ],
    ],

];
