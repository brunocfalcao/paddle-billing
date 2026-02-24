<?php

declare(strict_types=1);

namespace Brunocfalcao\PaddleBilling\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Console command to install and configure the paddle-billing package.
 *
 * Guides the user through publishing config/migrations, collecting Paddle
 * live/sandbox credentials, webhook URLs, and optional services (Pushover,
 * ZeptoMail). Updates or appends .env and runs migrations. Idempotent —
 * safe to run multiple times; updates existing keys in-place.
 */
class InstallCommand extends Command
{
    protected $signature = 'paddle-billing:install';

    protected $description = 'Install and configure paddle-billing — one command, fully wired. Safe to run multiple times.';

    /**
     * Cached .env values keyed by variable name.
     *
     * @var array<string, string>
     */
    private array $existingEnv = [];

    public function handle(): int
    {
        $this->info('🏗  Installing paddle-billing...');
        $this->newLine();

        $this->loadExistingEnv();

        // 1. Publish config and migrations.
        if (! file_exists(config_path('paddle-billing.php'))) {
            $this->call('vendor:publish', ['--tag' => 'paddle-billing-config']);
        } else {
            $this->line('  <comment>config/paddle-billing.php already exists — skipping publish (preserves your listeners).</comment>');
        }

        $paddleMigrationsExist = count(glob(database_path('migrations/*_create_paddle_events_table.php'))) > 0
            && count(glob(database_path('migrations/*_create_paddle_billing_tables.php'))) > 0;
        if (! $paddleMigrationsExist) {
            $this->call('vendor:publish', ['--tag' => 'paddle-billing-migrations']);
        }

        $cashierMigrationExists = count(glob(database_path('migrations/*_create_customers_table.php'))) > 0;
        if (! $cashierMigrationExists) {
            $this->call('vendor:publish', ['--tag' => 'cashier-migrations']);
        }

        $env = [];

        // 2. Sandbox toggle.
        $currentSandbox = ($this->existingEnv['PADDLE_SANDBOX'] ?? 'true') === 'true';
        $sandbox = $this->confirm('Do you also want to enable sandbox testing?', $currentSandbox);
        $env['PADDLE_SANDBOX'] = $sandbox ? 'true' : 'false';

        // Compute webhook URLs early so we can reference them in credential prompts.
        $appUrl = rtrim(config('app.url', 'https://yoursite.com'), '/');
        $livePath = config('paddle-billing.path', 'webhooks/paddle');
        $sandboxPath = config('paddle-billing.sandbox_path', 'webhooks/paddle-sandbox');
        $liveWebhookUrl = $appUrl.'/'.$livePath.'/webhook';
        $sandboxWebhookUrl = $appUrl.'/'.$sandboxPath.'/webhook';

        // 3. Live credentials.
        $this->newLine();
        $this->info('── Live (Production) Credentials ──');
        $this->line('  <comment>Get these from your PRODUCTION dashboard: https://vendors.paddle.com</comment>');
        $this->line('  <comment>These are your real credentials — used when sandbox mode is OFF.</comment>');
        $this->newLine();
        $env['PADDLE_SELLER_ID'] = $this->envAskNumeric('PADDLE_SELLER_ID', 'Live Seller ID <comment>(Sidebar > Your company name at the top > ⋮ menu > Seller ID)</comment>');
        $env['PADDLE_API_KEY'] = $this->envAskWithPrefix('PADDLE_API_KEY', 'Live Server-side API Key <comment>(Developer Tools > Authentication > API keys)</comment>', 'pdl_live_apikey_');
        $env['PADDLE_CLIENT_SIDE_TOKEN'] = $this->envAskWithPrefix('PADDLE_CLIENT_SIDE_TOKEN', 'Live Client-side Token <comment>(Developer Tools > Authentication > Client-side tokens)</comment>', 'live_');
        $this->newLine();
        $this->line('  <comment>Before entering the webhook secret, create a notification destination in Paddle:</comment>');
        $this->line('  <comment>1. Go to Developer Tools > Notifications > + New destination</comment>');
        $this->line("  <comment>2. Set the URL to: <info>{$liveWebhookUrl}</info></comment>");
        $this->line('  <comment>3. Under "Events", select all events (read & write)</comment>');
        $this->line('  <comment>4. Save, then copy the Secret key shown</comment>');
        $env['PADDLE_WEBHOOK_SECRET'] = $this->envAskWithPrefix('PADDLE_WEBHOOK_SECRET', 'Live Webhook Secret', 'pdl_ntfset_');
        $env['PADDLE_PRICE_ID'] = $this->envAskWithPrefix('PADDLE_PRICE_ID', 'Live Price ID <comment>(Catalog > Prices)</comment>', 'pri_');

        // 4. Sandbox credentials.
        $this->newLine();
        $this->info('── Sandbox (Testing) Credentials ──');
        $this->line('  <comment>Get these from your SANDBOX dashboard: https://sandbox-vendors.paddle.com</comment>');
        $this->line('  <comment>These are test-only credentials — used when sandbox mode is ON.</comment>');
        $this->newLine();
        $env['PADDLE_SANDBOX_SELLER_ID'] = $this->envAskNumeric('PADDLE_SANDBOX_SELLER_ID', 'Sandbox Seller ID <comment>(Sidebar > Your company name at the top > ⋮ menu > Seller ID)</comment>');
        $env['PADDLE_SANDBOX_API_KEY'] = $this->envAskWithPrefix('PADDLE_SANDBOX_API_KEY', 'Sandbox Server-side API Key <comment>(Developer Tools > Authentication > API keys)</comment>', 'pdl_sdbx_apikey_');
        $env['PADDLE_SANDBOX_CLIENT_SIDE_TOKEN'] = $this->envAskWithPrefix('PADDLE_SANDBOX_CLIENT_SIDE_TOKEN', 'Sandbox Client-side Token <comment>(Developer Tools > Authentication > Client-side tokens)</comment>', 'test_');
        $this->newLine();
        $this->line('  <comment>Before entering the webhook secret, create a notification destination in Paddle Sandbox:</comment>');
        $this->line('  <comment>1. Go to Developer Tools > Notifications > + New destination</comment>');
        $this->line("  <comment>2. Set the URL to: <info>{$sandboxWebhookUrl}</info></comment>");
        $this->line('  <comment>3. Under "Events", select all events (read & write)</comment>');
        $this->line('  <comment>4. Save, then copy the Secret key shown</comment>');
        $env['PADDLE_SANDBOX_WEBHOOK_SECRET'] = $this->envAskWithPrefix('PADDLE_SANDBOX_WEBHOOK_SECRET', 'Sandbox Webhook Secret', 'pdl_ntfset_');
        $env['PADDLE_SANDBOX_PRICE_ID'] = $this->envAskWithPrefix('PADDLE_SANDBOX_PRICE_ID', 'Sandbox Price ID <comment>(Catalog > Prices)</comment>', 'pri_');

        // 5. Webhook URLs (set automatically, no prompts needed).
        $env['PADDLE_WEBHOOK_URL'] = $liveWebhookUrl;
        $env['CASHIER_PATH'] = $livePath;
        $env['CASHIER_SANDBOX_PATH'] = $sandboxPath;

        // 6. Retain key (optional).
        if ($this->confirm('Configure Paddle Retain (ProfitWell)?', isset($this->existingEnv['PADDLE_RETAIN_KEY']))) {
            $env['PADDLE_RETAIN_KEY'] = $this->envAsk('PADDLE_RETAIN_KEY', 'Retain Key');
        }

        // 7. Currency.
        $env['CASHIER_CURRENCY'] = $this->envAsk('CASHIER_CURRENCY', 'Currency code', 'USD');

        // 8. Pushover (optional).
        $this->newLine();
        if ($this->confirm('Enable Pushover notifications on payment?', isset($this->existingEnv['PUSHOVER_APP_KEY']))) {
            $env['PUSHOVER_APP_KEY'] = $this->envAsk('PUSHOVER_APP_KEY', 'Pushover App Key');
            $env['PUSHOVER_USER_KEY'] = $this->envAsk('PUSHOVER_USER_KEY', 'Pushover User Key');
        }

        // 9. ZeptoMail (optional).
        $this->newLine();
        if ($this->confirm('Use ZeptoMail for transactional email?', isset($this->existingEnv['ZEPTOMAIL_MAIL_KEY']))) {
            $env['MAIL_MAILER'] = 'zeptomail';
            $env['MAIL_FROM_ADDRESS'] = $this->envAsk('MAIL_FROM_ADDRESS', 'Default From address <comment>(e.g. hello@yourdomain.com)</comment>');
            $env['MAIL_FROM_NAME'] = $this->envAsk('MAIL_FROM_NAME', 'Default From name <comment>(e.g. Inomem)</comment>');
            $env['ZEPTOMAIL_MAIL_KEY'] = $this->envAsk('ZEPTOMAIL_MAIL_KEY', 'ZeptoMail API key <comment>(from ZeptoMail dashboard > Mail Agents > Send Mail API token)</comment>');
            $this->injectZeptoMailConfig();
        }

        // 10. Write .env.
        $this->newLine();
        $this->writeEnv($env);

        // 11. Run migrations.
        if ($this->confirm('Run migrations now?', true)) {
            $this->call('migrate');
        }

        // 12. Summary.
        $this->newLine();
        $this->info('✅ paddle-billing installed!');
        $this->newLine();
        $this->line('  <comment>Next steps:</comment>');
        $this->line('  1. Set webhook URLs in Paddle dashboards:');
        $this->line("     Live    → <info>{$liveWebhookUrl}</info>");
        $this->line("     Sandbox → <info>{$sandboxWebhookUrl}</info>");
        $this->line('  2. Subscribe to these events: transaction.paid, transaction.updated, transaction.completed');
        $this->line('  3. Add your listeners in <info>config/paddle-billing.php</info> → "listeners"');
        $this->line('  4. Add <info>@paddleJs</info> and <info>@paddleInit</info> to your checkout Blade view');
        $this->line('  5. Use the <info>HasPaddleCheckout</info> trait on your billable model');

        return self::SUCCESS;
    }

    /**
     * Ask for a value that must start with a specific prefix, re-prompting on mismatch.
     */
    private function envAskWithPrefix(string $key, string $question, string $prefix): string
    {
        $existing = $this->existingEnv[$key] ?? null;

        while (true) {
            $value = $this->ask("{$question} <comment>(must start with {$prefix})</comment>", $existing);

            if ($value !== null && str_starts_with($value, $prefix)) {
                return $value;
            }

            $this->error("  Invalid value — must start with \"{$prefix}\". Please try again.");
        }
    }

    /**
     * Prompt for a numeric value, pre-filling with existing .env value.
     *
     * Loops until valid numeric input is received. Shows error message
     * and re-prompts on non-numeric input.
     */
    private function envAskNumeric(string $key, string $question): string
    {
        $existing = $this->existingEnv[$key] ?? null;

        while (true) {
            $value = $this->ask($question, $existing);

            if ($value !== null && ctype_digit($value)) {
                return $value;
            }

            $this->error('  Seller ID must be a numeric value (e.g. 12345). Please try again.');
        }
    }

    /**
     * Prompt for a value, pre-filling with existing .env value if present.
     *
     * Falls back to a default value if neither existing nor user input is provided.
     */
    private function envAsk(string $key, string $question, string $fallback = ''): string
    {
        $existing = $this->existingEnv[$key] ?? null;
        $default = $existing ?: ($fallback ?: null);

        return $this->ask($question, $default) ?? $fallback;
    }

    /**
     * Parse the current .env file into key-value pairs.
     */
    private function loadExistingEnv(): void
    {
        $envPath = $this->laravel->basePath('.env');

        if (! file_exists($envPath)) {
            return;
        }

        foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            if (str_starts_with(trim($line), '#')) {
                continue;
            }

            if (! str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $this->existingEnv[trim($key)] = trim(trim($value), '"\'');
        }
    }

    /**
     * Write env values — updates existing keys in-place, appends new ones.
     */
    private function writeEnv(array $values): void
    {
        $envPath = $this->laravel->basePath('.env');

        if (! file_exists($envPath)) {
            $this->warn('.env file not found — skipping.');

            return;
        }

        $envContent = file_get_contents($envPath);
        $toAppend = [];
        $updated = 0;

        foreach ($values as $key => $value) {
            $formatted = $this->formatEnvValue($key, $value);
            $pattern = '/^'.preg_quote($key, '/').'=.*/m';

            if (preg_match($pattern, $envContent)) {
                $envContent = preg_replace($pattern, $formatted, $envContent, 1);
                $updated++;
            } else {
                $toAppend[] = $formatted;
            }
        }

        // Append new keys under a Paddle Billing header (only if there are new keys).
        if ($toAppend) {
            if (! Str::contains($envContent, '# Paddle Billing')) {
                $envContent .= "\n# Paddle Billing\n";
            } else {
                $envContent = rtrim($envContent)."\n";
            }

            $envContent .= implode("\n", $toAppend)."\n";
        }

        file_put_contents($envPath, $envContent);

        if ($updated > 0) {
            $this->info("  Updated {$updated} existing variable(s) in .env");
        }

        if ($toAppend) {
            $this->info('  Appended '.count($toAppend).' new variable(s) to .env');
        }

        if ($updated === 0 && ! $toAppend) {
            $this->line('  <comment>No .env changes needed</comment>');
        }
    }

    /**
     * Format an .env variable for writing to file.
     *
     * Quotes values containing spaces, special characters, or starting with quotes.
     */
    private function formatEnvValue(string $key, string $value): string
    {
        $hasSpecialChars = Str::contains($value, [' ', '#', '"', '+', '=']) || str_starts_with($value, "'");
        $needsQuotes = $value !== '' && $hasSpecialChars;

        return $needsQuotes ? "{$key}=\"{$value}\"" : "{$key}={$value}";
    }

    /**
     * Inject the zeptomail mailer entry into config/mail.php if not already present.
     */
    private function injectZeptoMailConfig(): void
    {
        $configPath = $this->laravel->configPath('mail.php');

        if (! file_exists($configPath)) {
            $this->warn('config/mail.php not found — skipping ZeptoMail config injection.');

            return;
        }

        $content = file_get_contents($configPath);

        if (str_contains($content, "'zeptomail'")) {
            $this->line('  <comment>ZeptoMail mailer entry already present in config/mail.php</comment>');

            return;
        }

        $entry = "\n        'zeptomail' => [\n            'transport' => 'zeptomail',\n        ],\n";

        $content = preg_replace(
            "/(        'array' => \[\n            'transport' => 'array',\n        \],)/",
            "$1{$entry}",
            $content,
            1,
        );

        file_put_contents($configPath, $content);

        $this->info('  Injected zeptomail mailer into config/mail.php');
    }
}
