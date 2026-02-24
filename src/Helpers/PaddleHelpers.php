<?php

declare(strict_types=1);

if (! function_exists('paddle_price_id')) {
    /**
     * Get the active price ID (sandbox or live) from config.
     */
    function paddle_price_id(): ?string
    {
        $key = config('paddle-billing.sandbox')
            ? 'paddle-billing.sandbox_credentials.price_id'
            : 'paddle-billing.live.price_id';

        return config($key);
    }
}

if (! function_exists('paddle_checkout_url')) {
    /**
     * Get the active checkout URL override (sandbox or live) from config.
     */
    function paddle_checkout_url(): ?string
    {
        $key = config('paddle-billing.sandbox')
            ? 'paddle-billing.sandbox_credentials.checkout_url'
            : 'paddle-billing.live.checkout_url';

        return config($key);
    }
}

if (! function_exists('paddle_checkout_options')) {
    /**
     * Encode checkout options as a JSON string for use in JS.
     *
     * @param  mixed  $checkout  A Cashier Checkout instance or options array.
     */
    function paddle_checkout_options(mixed $checkout): string
    {
        $options = is_array($checkout) ? $checkout : $checkout->options();

        return json_encode($options, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }
}
