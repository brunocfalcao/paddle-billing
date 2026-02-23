<?php

declare(strict_types=1);

namespace Brunocfalcao\PaddleBilling\Listeners;

use Brunocfalcao\PaddleBilling\Models\PaddleEvent;
use Laravel\Paddle\Events\WebhookReceived;

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
    }
}
