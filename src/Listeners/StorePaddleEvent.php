<?php

declare(strict_types=1);

namespace Brunocfalcao\PaddleBilling\Listeners;

use Brunocfalcao\PaddleBilling\Events\PurchaseCompleted;
use Brunocfalcao\PaddleBilling\Models\PaddleEvent;
use Illuminate\Support\Facades\Event;
use Laravel\Paddle\Events\WebhookReceived;

/**
 * Listener for WebhookReceived events from Laravel Cashier.
 *
 * Stores all webhook events in the database and dispatches a custom
 * PurchaseCompleted event for transaction.completed webhooks with
 * structured data (models, custom data) for downstream listeners.
 */
class StorePaddleEvent
{
    public function handle(WebhookReceived $event): void
    {
        $paddleEventId = $event->payload['event_id'] ?? '';

        if ($paddleEventId && PaddleEvent::where('paddle_event_id', $paddleEventId)->exists()) {
            return;
        }

        PaddleEvent::create([
            'event_type' => $event->payload['event_type'] ?? 'unknown',
            'paddle_event_id' => $paddleEventId,
            'payload' => $event->payload,
        ]);

        if (($event->payload['event_type'] ?? '') === 'transaction.completed') {
            $this->handleTransactionCompleted($event->payload);
        }
    }

    /**
     * Process a transaction.completed webhook payload.
     *
     * Creates or updates customer and product records, then creates a
     * purchase record with associated metadata. Dispatches a PurchaseCompleted
     * event for app-level listeners to act on.
     */
    private function handleTransactionCompleted(array $payload): void
    {
        $data = $payload['data'] ?? [];
        $customerData = $data['customer'] ?? [];
        $items = $data['items'] ?? [];
        $customData = $data['custom_data'] ?? [];
        $transactionId = $data['id'] ?? '';

        $customerModel = config('paddle-billing.models.customer');
        $productModel = config('paddle-billing.models.product');
        $purchaseModel = config('paddle-billing.models.purchase');

        $customer = $customerModel::updateOrCreate(
            ['paddle_customer_id' => $customerData['id'] ?? ''],
            [
                'email' => $customerData['email'] ?? '',
                'name' => $customerData['name'] ?? null,
            ]
        );

        $priceData = $items[0]['price'] ?? [];
        $product = $productModel::updateOrCreate(
            ['paddle_price_id' => $priceData['id'] ?? ''],
            [
                'name' => $priceData['name'] ?? 'Unknown',
                'description' => $priceData['description'] ?? null,
                'price' => $priceData['unit_price']['amount'] ?? 0,
                'currency' => $data['currency_code'] ?? 'USD',
            ]
        );

        $purchase = $purchaseModel::create([
            'customer_id' => $customer->id,
            'product_id' => $product->id,
            'paddle_transaction_id' => $transactionId,
            'status' => $data['status'] ?? 'completed',
            'invoice_url' => $data['invoice_url'] ?? null,
        ]);

        foreach ($customData as $key => $value) {
            $purchase->metadata()->create([
                'key' => $key,
                'value' => is_array($value) ? json_encode($value) : (string) $value,
            ]);
        }

        Event::dispatch(new PurchaseCompleted($purchase, $customer, $product, $customData));
    }
}
