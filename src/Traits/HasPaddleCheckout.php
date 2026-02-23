<?php

declare(strict_types=1);

namespace Brunocfalcao\PaddleBilling\Traits;

trait HasPaddleCheckout
{
    public function isSandbox(): bool
    {
        return (bool) config('paddle-billing.sandbox', false);
    }

    /**
     * Build the options array for Paddle.Checkout.open().
     *
     * @param  array<string, mixed>  $customData
     * @return array<string, mixed>
     */
    public function paddleCheckoutOptions(
        string $priceId,
        array $customData = [],
        ?string $returnUrl = null,
    ): array {
        $checkout = $this->checkout([$priceId])
            ->customData($customData);

        if ($returnUrl) {
            $checkout->returnUrl($returnUrl);
        }

        return $checkout->options();
    }
}
