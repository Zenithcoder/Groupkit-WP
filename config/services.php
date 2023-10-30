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

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'stripe' => [
        'default' => [
            'key' => env('STRIPE_KEY'),
            'secret' => env('STRIPE_SECRET'),
            'webhook' => [
                'secret' => env('STRIPE_WEBHOOK_SECRET'),
                'tolerance' => env('STRIPE_WEBHOOK_TOLERANCE', 300),
            ],
        ],
        'legacy' => [
            'key' => env('STRIPE_LEGACY_KEY'),
            'secret' => env('STRIPE_LEGACY_SECRET'),
        ],
        'new' => [
            'key' => env('STRIPE_NEW_KEY'),
            'secret' => env('STRIPE_NEW_SECRET'),
            'webhook' => [
                'secret' => env('STRIPE_NEW_WEBHOOK_SECRET'),
                'tolerance' => env('STRIPE_NEW_WEBHOOK_TOLERANCE', 300),
            ],
        ]
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT'),
    ],

    'tapfiliate' => [
        'key' => env('TAPFILIATE_KEY'),
        'url' => "https://api.tapfiliate.com/1.6/",
    ],

    'facebook'      => [
        'client_id'     => env('FACEBOOK_CLIENT_ID'),
        'client_secret' => env('FACEBOOK_CLIENT_SECRET'),
        'redirect'      => env('FACEBOOK_REDIRECT_URL', '/login/facebook/callback'),
    ],

    'aweber' => [
        'list' => [
            'order_bump'          => env('AWEBER_ORDER_BUMP_LIST'),
            'plan_H81yEbnL2c1ng6' => env('AWEBER_BASIC_MONTHLY_LIST'), #BASIC
            'plan_H81ycCkDlKy6Ng' => env('AWEBER_PRO_MONTHLY_LIST'), #PRO_MONTHLY
            'plan_H98gMql8UbiAgb' => env('AWEBER_PRO_YEARLY_LIST'), #PRO_ANNUAL
        ]
    ],
];
