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

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
        'auth_redirect' => env('GOOGLE_AUTH_REDIRECT_URI'),
    ],

    'email_validation' => [
        'key' => env('EMAIL_VALIDATION_API_KEY'),
    ],

    'github' => [
        'token' => env('GITHUB_TOKEN'),
    ],

    'brave_search' => [
        'key' => env('BRAVE_SEARCH_API_KEY'),
        'result_limit' => env('BRAVE_SEARCH_RESULT_LIMIT', 10),
    ],

    'tavily' => [
        'key' => env('TAVILY_API_KEY'),
        'result_limit' => env('TAVILY_SEARCH_RESULT_LIMIT', 10),
    ],

    'pdl' => [
        'enabled' => env('PDL_ENABLED', false),
        'api_key' => env('PDL_API_KEY'),
        'result_limit' => env('PDL_SEARCH_RESULT_LIMIT', 10),
    ],

];
