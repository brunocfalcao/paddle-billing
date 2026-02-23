<?php

declare(strict_types=1);

namespace Brunocfalcao\PaddleBilling\Events;

use Illuminate\Database\Eloquent\Model;

/**
 * Event fired when a Paddle transaction.completed webhook is fully processed.
 *
 * Carries structured data extracted from the webhook payload. Models are
 * typed as base Eloquent to keep the package decoupled from the consuming
 * app's namespace. App listeners (email, access grants, etc.) receive
 * ready-to-use models rather than raw payload arrays.
 *
 * @see \Brunocfalcao\PaddleBilling\Listeners\StorePaddleEvent
 */
final class PurchaseCompleted
{
    public function __construct(
        public readonly Model $purchase,
        public readonly Model $customer,
        public readonly Model $product,
        public readonly array $customData,
    ) {}
}
