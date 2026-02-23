<?php

declare(strict_types=1);

namespace Brunocfalcao\PaddleBilling\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class InstallCommand extends Command
{
    protected $signature = 'paddle-billing:install';

    protected $description = 'Install and configure paddle-billing — one command, fully wired.';

    public function handle(): int
    {
        $this->info('🏗  Installing paddle-billing...');
        $this->newLine();

        // 1. Publish config and migrations.
        $this->call('vendor:publish', ['--tag' => 'paddle-billing-config', '--force' => true]);
        $this->call('vendor:publish', ['--tag' => 'paddle-billing-migrations']);

        $env = [];

        // 2. Sandbox toggle.
        $sandbox = $this->confirm('Enable sandbox mode?', true);
        $env['PADDLE_SANDBOX'] = $sandbox ? 'true' : 'false';

        // 3. Live credentials.
        $this->newLine();
        $this->info('── Live Credentials ──');
        $this->line('  <comment>Find these at: https://vendors.paddle.com/authentication</comment>');
        $env['PADDLE_SELLER_ID'] = $this->ask('Live Seller ID') ?? '';
        $env['PADDLE_API_KEY'] = $this->ask('Live API Key') ?? '';
        $env['PADDLE_CLIENT_SIDE_TOKEN'] = $this->ask('Live Client-side Token') ?? '';
        $env['PADDLE_WEBHOOK_SECRET'] = $this->ask('Live Webhook Secret (from notification settings)') ?? '';
        $env['PADDLE_PRICE_ID'] = $this->ask('Live Price ID (pri_...)') ?? '';

        // 4. Sandbox credentials.
        $this->newLine();
        $this->info('── Sandbox Credentials ──');
        $this->line('  <comment>Find these at: https://sandbox-vendors.paddle.com/authentication</comment>');
        $env['PADDLE_SANDBOX_SELLER_ID'] = $this->ask('Sandbox Seller ID') ?? '';
        $env['PADDLE_SANDBOX_API_KEY'] = $this->ask('Sandbox API Key') ?? '';
        $env['PADDLE_SANDBOX_CLIENT_SIDE_TOKEN'] = $this->ask('Sandbox Client-side Token') ?? '';
        $env['PADDLE_SANDBOX_WEBHOOK_SECRET'] = $this->ask('Sandbox Webhook Secret') ?? '';
        $env['PADDLE_SANDBOX_PRICE_ID'] = $this->ask('Sandbox Price ID (pri_...)') ?? '';

        // 5. Webhook URL.
        $this->newLine();
        $this->info('── Webhook Configuration ──');
        $appUrl = config('app.url', 'https://yoursite.com');
        $defaultWebhookUrl = rtrim($appUrl, '/') . '/' . config('paddle-billing.path', 'paddle');
        $env['PADDLE_WEBHOOK_URL'] = $this->ask('Full webhook URL (what you set in Paddle dashboard)', $defaultWebhookUrl) ?? '';
        $env['CASHIER_PATH'] = $this->ask('Webhook route path', 'paddle') ?? 'paddle';

        // 6. Retain key (optional).
        if ($this->confirm('Configure Paddle Retain (ProfitWell)?', false)) {
            $env['PADDLE_RETAIN_KEY'] = $this->ask('Retain Key') ?? '';
        }

        // 7. Currency.
        $env['CASHIER_CURRENCY'] = $this->ask('Currency code', 'USD') ?? 'USD';

        // 8. Pushover (optional).
        $this->newLine();
        if ($this->confirm('Enable Pushover notifications on payment?', false)) {
            $env['PUSHOVER_APP_KEY'] = $this->ask('Pushover App Key') ?? '';
            $env['PUSHOVER_USER_KEY'] = $this->ask('Pushover User Key') ?? '';
        }

        // 9. Write .env.
        $this->newLine();
        $this->appendToEnv($env);

        // 10. Run migrations.
        if ($this->confirm('Run migrations now?', true)) {
            $this->call('migrate');
        }

        // 11. Summary.
        $this->newLine();
        $this->info('✅ paddle-billing installed!');
        $this->newLine();
        $this->line('  <comment>Next steps:</comment>');
        $this->line("  1. Set webhook URL in Paddle dashboard → <info>{$env['PADDLE_WEBHOOK_URL']}</info>");
        $this->line('  2. Subscribe to these events: transaction.paid, transaction.updated, transaction.completed');
        $this->line('  3. Add your listeners in <info>config/paddle-billing.php</info> → "listeners"');
        $this->line('  4. Add <info>@paddleJs</info> and <info>@paddleInit</info> to your checkout Blade view');
        $this->line('  5. Use the <info>HasPaddleCheckout</info> trait on your billable model');

        return self::SUCCESS;
    }

    private function appendToEnv(array $values): void
    {
        $envPath = $this->laravel->basePath('.env');

        if (! file_exists($envPath)) {
            $this->warn('.env file not found — skipping.');

            return;
        }

        $envContent = file_get_contents($envPath);
        $appended = [];
        $skipped = 0;

        foreach ($values as $key => $value) {
            if (Str::contains($envContent, $key . '=')) {
                $skipped++;

                continue;
            }

            // Quote values that contain spaces or special characters.
            $needsQuotes = $value !== '' && (
                Str::contains($value, [' ', '#', '"', '+', '=']) ||
                str_starts_with($value, "'")
            );

            $appended[] = $needsQuotes ? "{$key}=\"{$value}\"" : "{$key}={$value}";
        }

        if ($skipped > 0) {
            $this->line("  <comment>Skipped {$skipped} variable(s) already in .env</comment>");
        }

        if ($appended) {
            $block = "\n# Paddle Billing\n" . implode("\n", $appended) . "\n";
            file_put_contents($envPath, $envContent . $block);
            $this->info("  Appended " . count($appended) . " variable(s) to .env");
        }
    }
}
