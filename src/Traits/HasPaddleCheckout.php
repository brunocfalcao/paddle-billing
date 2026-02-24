<?php

declare(strict_types=1);

namespace Brunocfalcao\PaddleBilling\Traits;

use Laravel\Paddle\Cashier;

trait HasPaddleCheckout
{
    public function isSandbox(): bool
    {
        return (bool) config('paddle-billing.sandbox', false);
    }

    /**
     * Build the options array for Paddle.Checkout.open().
     *
     * When a checkout_url is configured, creates the transaction server-side
     * via Paddle API so the checkout.url override takes effect (multi-domain).
     * Otherwise falls back to client-side item resolution.
     *
     * @param  array<string, mixed>  $customData
     * @return array<string, mixed>
     */
    public function paddleCheckoutOptions(
        string $priceId,
        array $customData = [],
        ?string $returnUrl = null,
    ): array {
        $checkoutUrl = paddle_checkout_url();

        if ($checkoutUrl) {
            return $this->serverSideCheckoutOptions($priceId, $customData, $returnUrl, $checkoutUrl);
        }

        return $this->clientSideCheckoutOptions($priceId, $customData, $returnUrl);
    }

    /**
     * Create a transaction server-side and return options with transactionId.
     */
    private function serverSideCheckoutOptions(
        string $priceId,
        array $customData,
        ?string $returnUrl,
        string $checkoutUrl,
    ): array {
        $customer = $this->createAsCustomer();

        $payload = [
            'items' => Cashier::normalizeItems([$priceId], 'price_id'),
            'customer_id' => $customer->paddle_id,
            'checkout' => ['url' => $checkoutUrl],
        ];

        if ($customData) {
            $payload['custom_data'] = $customData;
        }

        $response = Cashier::api('POST', 'transactions', $payload);
        $transactionId = $response->json('data.id');

        $options = [
            'transactionId' => $transactionId,
            'settings' => array_filter([
                'displayMode' => 'inline',
                'frameStyle' => 'width: 100%; background-color: transparent; border: none;',
                'successUrl' => $returnUrl,
                'allowLogout' => false,
            ]),
        ];

        return $options;
    }

    /**
     * Fall back to client-side checkout (original behavior).
     */
    private function clientSideCheckoutOptions(
        string $priceId,
        array $customData,
        ?string $returnUrl,
    ): array {
        $checkout = $this->checkout([$priceId])
            ->customData($customData);

        if ($returnUrl) {
            $checkout->returnTo($returnUrl);
        }

        return $checkout->options();
    }
}
