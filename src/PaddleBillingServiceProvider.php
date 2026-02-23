<?php

declare(strict_types=1);

namespace Brunocfalcao\PaddleBilling;

use Brunocfalcao\PaddleBilling\Console\InstallCommand;
use Brunocfalcao\PaddleBilling\Listeners\StorePaddleEvent;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Laravel\Paddle\Events\WebhookReceived;

class PaddleBillingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/paddle-billing.php', 'paddle-billing');
    }

    public function boot(): void
    {
        $this->configureCashier();
        $this->registerPublishables();
        $this->registerListeners();
        $this->registerBladeDirectives();
        $this->registerCommands();
    }

    private function configureCashier(): void
    {
        $sandbox = (bool) config('paddle-billing.sandbox', false);
        $credentials = $sandbox
            ? config('paddle-billing.sandbox_credentials')
            : config('paddle-billing.live');

        config([
            'cashier.seller_id' => $credentials['seller_id'] ?? null,
            'cashier.client_side_token' => $credentials['client_side_token'] ?? null,
            'cashier.api_key' => $credentials['api_key'] ?? null,
            'cashier.webhook_secret' => $credentials['webhook_secret'] ?? null,
            'cashier.sandbox' => $sandbox,
            'cashier.path' => config('paddle-billing.path', 'paddle'),
            'cashier.currency' => config('paddle-billing.currency', 'USD'),
            'cashier.currency_locale' => config('paddle-billing.currency_locale', 'en'),
            'cashier.retain_key' => config('paddle-billing.retain_key'),
        ]);
    }

    private function registerPublishables(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/paddle-billing.php' => config_path('paddle-billing.php'),
            ], 'paddle-billing-config');

            $this->publishesMigrations([
                __DIR__ . '/../database/migrations' => database_path('migrations'),
            ], 'paddle-billing-migrations');
        }
    }

    private function registerListeners(): void
    {
        // Always log all webhook events.
        Event::listen(WebhookReceived::class, StorePaddleEvent::class);

        // Register app-specific listeners from config.
        $listeners = config('paddle-billing.listeners', []);

        foreach ($listeners as $eventType => $listenerClass) {
            Event::listen(WebhookReceived::class, function (WebhookReceived $event) use ($eventType, $listenerClass) {
                $receivedType = $event->payload['event_type'] ?? '';

                if ($receivedType === $eventType) {
                    app($listenerClass)->handle($event);
                }
            });
        }
    }

    private function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([InstallCommand::class]);
        }
    }

    private function registerBladeDirectives(): void
    {
        Blade::directive('paddleJs', function () {
            return '<script src="https://cdn.paddle.com/paddle/v2/paddle.js"></script>';
        });

        Blade::directive('paddleInit', function () {
            return "<?php
                if (config('paddle-billing.sandbox')) {
                    echo '<script>Paddle.Environment.set(\"sandbox\");</script>';
                }
                echo '<script>Paddle.Initialize({ token: ' . json_encode(config('cashier.client_side_token')) . ' });</script>';
            ?>";
        });
    }
}
