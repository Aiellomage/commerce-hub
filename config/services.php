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

    'magento' => [
        'timeout' => (int) env('MAGENTO_TIMEOUT', 30),
        'ca_bundle' => env('MAGENTO_CA_BUNDLE'),

        // Local development store, created by the seeder when the credentials are set.
        'dev_store' => [
            'url' => env('MAGENTO_URL'),
            'consumer_key' => env('MAGENTO_CONSUMER_KEY'),
            'consumer_secret' => env('MAGENTO_CONSUMER_SECRET'),
            'access_token' => env('MAGENTO_ACCESS_TOKEN'),
            'access_token_secret' => env('MAGENTO_ACCESS_TOKEN_SECRET'),
        ],
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
