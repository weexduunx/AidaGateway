<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Gateway
    |--------------------------------------------------------------------------
    |
    | This option defines the default payment gateway that will be used.
    | You may set this to any of the gateways defined in the "gateways" array.
    |
    */

    'default' => env('AIDA_DEFAULT_GATEWAY', 'orange_money'),

    /*
    |--------------------------------------------------------------------------
    | Gateways Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure all of the mobile money gateways used by your
    | application. A default configuration has been provided for each gateway.
    |
    */

    'gateways' => [

        'orange_money' => [
            'enabled' => env('AIDA_ORANGE_MONEY_ENABLED', true),
            'api_url' => env('AIDA_ORANGE_MONEY_API_URL', 'https://api.orange.com/orange-money-webpay'),
            'merchant_key' => env('AIDA_ORANGE_MONEY_MERCHANT_KEY'),
            'api_username' => env('AIDA_ORANGE_MONEY_API_USERNAME'),
            'api_password' => env('AIDA_ORANGE_MONEY_API_PASSWORD'),
            'currency' => env('AIDA_ORANGE_MONEY_CURRENCY', 'XOF'),
            'country_code' => env('AIDA_ORANGE_MONEY_COUNTRY_CODE', 'SN'),
        ],

        'wave' => [
            'enabled' => env('AIDA_WAVE_ENABLED', true),
            'api_url' => env('AIDA_WAVE_API_URL', 'https://api.wave.com'),
            'api_key' => env('AIDA_WAVE_API_KEY'),
            'api_secret' => env('AIDA_WAVE_API_SECRET'),
            'currency' => env('AIDA_WAVE_CURRENCY', 'XOF'),
        ],

        'free_money' => [
            'enabled' => env('AIDA_FREE_MONEY_ENABLED', true),
            'api_url' => env('AIDA_FREE_MONEY_API_URL', 'https://api.free.sn'),
            'merchant_id' => env('AIDA_FREE_MONEY_MERCHANT_ID'),
            'api_key' => env('AIDA_FREE_MONEY_API_KEY'),
            'api_secret' => env('AIDA_FREE_MONEY_API_SECRET'),
            'currency' => env('AIDA_FREE_MONEY_CURRENCY', 'XOF'),
        ],

        'emoney' => [
            'enabled' => env('AIDA_EMONEY_ENABLED', true),
            'api_url' => env('AIDA_EMONEY_API_URL', 'https://api.emoney.sn'),
            'merchant_code' => env('AIDA_EMONEY_MERCHANT_CODE'),
            'api_key' => env('AIDA_EMONEY_API_KEY'),
            'api_secret' => env('AIDA_EMONEY_API_SECRET'),
            'currency' => env('AIDA_EMONEY_CURRENCY', 'XOF'),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Webhook Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the webhook settings for receiving payment confirmations.
    |
    */

    'webhook' => [
        'route_prefix' => env('AIDA_WEBHOOK_ROUTE_PREFIX', 'aida/webhooks'),
        'middleware' => ['api'],
        'secret' => env('AIDA_WEBHOOK_SECRET'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Transaction Settings
    |--------------------------------------------------------------------------
    |
    | Configure transaction-related settings.
    |
    */

    'transaction' => [
        'table_name' => 'aida_transactions',
        'timeout' => env('AIDA_TRANSACTION_TIMEOUT', 300), // 5 minutes
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    |
    | Enable or disable logging for debugging purposes.
    |
    */

    'logging' => [
        'enabled' => env('AIDA_LOGGING_ENABLED', true),
        'channel' => env('AIDA_LOGGING_CHANNEL', 'stack'),
    ],

];
