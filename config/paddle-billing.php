<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Sandbox Mode
    |--------------------------------------------------------------------------
    |
    | Toggle between Paddle sandbox and live environments. When true, sandbox
    | credentials and price IDs are used automatically.
    |
    */

    'sandbox' => env('PADDLE_SANDBOX', false),

    /*
    |--------------------------------------------------------------------------
    | Live Credentials
    |--------------------------------------------------------------------------
    */

    'live' => [
        'seller_id' => env('PADDLE_SELLER_ID'),
        'client_side_token' => env('PADDLE_CLIENT_SIDE_TOKEN'),
        'api_key' => env('PADDLE_API_KEY'),
        'webhook_secret' => env('PADDLE_WEBHOOK_SECRET'),
        'price_id' => env('PADDLE_PRICE_ID'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Sandbox Credentials
    |--------------------------------------------------------------------------
    */

    'sandbox_credentials' => [
        'seller_id' => env('PADDLE_SANDBOX_SELLER_ID'),
        'client_side_token' => env('PADDLE_SANDBOX_CLIENT_SIDE_TOKEN'),
        'api_key' => env('PADDLE_SANDBOX_API_KEY'),
        'webhook_secret' => env('PADDLE_SANDBOX_WEBHOOK_SECRET'),
        'price_id' => env('PADDLE_SANDBOX_PRICE_ID'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Retain Key (Paddle Retain / ProfitWell)
    |--------------------------------------------------------------------------
    */

    'retain_key' => env('PADDLE_RETAIN_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Webhook Path
    |--------------------------------------------------------------------------
    |
    | Base URI path for the Cashier webhook route.
    |
    */

    'path' => env('CASHIER_PATH', 'paddle'),
    'sandbox_path' => env('CASHIER_SANDBOX_PATH', 'paddle-sandbox'),

    /*
    |--------------------------------------------------------------------------
    | Webhook URL
    |--------------------------------------------------------------------------
    |
    | The full public URL Paddle sends webhooks to. Used during install to
    | remind you what to configure in the Paddle dashboard.
    |
    */

    'webhook_url' => env('PADDLE_WEBHOOK_URL'),

    /*
    |--------------------------------------------------------------------------
    | Currency
    |--------------------------------------------------------------------------
    */

    'currency' => env('CASHIER_CURRENCY', 'USD'),

    'currency_locale' => env('CASHIER_CURRENCY_LOCALE', 'en'),

    /*
    |--------------------------------------------------------------------------
    | Pushover Notifications (optional)
    |--------------------------------------------------------------------------
    |
    | Send a Pushover notification on payment success. Leave null to disable.
    |
    */

    'pushover' => [
        'app_key' => env('PUSHOVER_APP_KEY'),
        'user_key' => env('PUSHOVER_USER_KEY'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Event Handlers
    |--------------------------------------------------------------------------
    |
    | Map Paddle event types to listener classes. StorePaddleEvent is always
    | active. Add your app-specific listeners here.
    |
    | Example:
    |   'transaction.paid' => \App\Listeners\HandlePayment::class,
    |
    */

    'listeners' => [
        // 'transaction.paid' => \App\Listeners\HandlePayment::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Models
    |--------------------------------------------------------------------------
    |
    | The Eloquent model classes used when processing transaction.completed
    | webhooks. Override these if your app uses different namespaces or
    | custom model classes.
    |
    */

    'models' => [
        'customer' => \App\Models\Customer::class,
        'product'  => \App\Models\Product::class,
        'purchase' => \App\Models\Purchase::class,
    ],

];
