<?php

declare(strict_types=1);

namespace Brunocfalcao\PaddleBilling\Listeners;

use Brunocfalcao\PaddleBilling\Events\PurchaseCompleted;
use Brunocfalcao\PaddleBilling\Models\PaddleEvent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
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

        $paddleEvent = PaddleEvent::firstOrCreate(
            ['paddle_event_id' => $paddleEventId],
            [
                'event_type' => $event->payload['event_type'] ?? 'unknown',
                'payload' => $event->payload,
            ]
        );

        if (! $paddleEvent->wasRecentlyCreated) {
            return;
        }

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
        $customData = $data['custom_data'] ?? [];
        $transactionId = $data['id'] ?? '';

        // Extract customer and product data
        $paddleCustomerId = $data['customer_id'] ?? ($data['customer']['id'] ?? '');
        $customerData = $this->fetchPaddleCustomer($paddleCustomerId);

        $priceData = ($data['items'][0]['price'] ?? []);

        // Create or update models
        $customer = config('paddle-billing.models.customer')::updateOrCreate(
            ['paddle_id' => $paddleCustomerId],
            [
                'billable_type' => 'guest',
                'billable_id' => 0,
                'email' => $customerData['email'] ?? '',
                'name' => $customerData['name'] ?? '',
            ]
        );

        $product = config('paddle-billing.models.product')::updateOrCreate(
            ['paddle_price_id' => $priceData['id'] ?? ''],
            [
                'name' => $priceData['name'] ?? 'Unknown',
                'description' => $priceData['description'] ?? null,
                'price' => $priceData['unit_price']['amount'] ?? 0,
                'currency' => $data['currency_code'] ?? 'USD',
            ]
        );

        $purchase = config('paddle-billing.models.purchase')::create([
            'customer_id' => $customer->id,
            'product_id' => $product->id,
            'paddle_transaction_id' => $transactionId,
            'status' => $data['status'] ?? 'completed',
            'invoice_url' => $data['invoice_url'] ?? null,
        ]);

        $this->createPurchaseMetadata($purchase, $customData);

        Event::dispatch(new PurchaseCompleted($purchase, $customer, $product, $customData));
    }

    /**
     * Fetch customer details from the Paddle API.
     */
    private function fetchPaddleCustomer(string $customerId): array
    {
        if (! $customerId) {
            return [];
        }

        $sandbox = (bool) config('paddle-billing.sandbox', false);
        $baseUrl = $sandbox ? 'https://sandbox-api.paddle.com' : 'https://api.paddle.com';
        $apiKey = $sandbox
            ? config('paddle-billing.sandbox_credentials.api_key')
            : config('paddle-billing.live.api_key');

        $response = Http::withToken($apiKey)->get("{$baseUrl}/customers/{$customerId}");

        return $response->successful() ? ($response->json('data') ?? []) : [];
    }

    /**
     * Create metadata records for a purchase from custom data.
     */
    private function createPurchaseMetadata($purchase, array $customData): void
    {
        foreach ($customData as $key => $value) {
            $purchase->metadata()->create([
                'key' => $key,
                'value' => is_array($value) ? json_encode($value) : (string) $value,
            ]);
        }
    }
}
