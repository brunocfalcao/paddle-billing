# Changelog

## [1.5.0] — 2026-02-24

### Features
- [NEW FEATURE] Multi-domain checkout URL override — `checkout_url` config key for both live and sandbox environments
- [NEW FEATURE] `paddle_checkout_url()` helper to resolve the active checkout URL (sandbox or live)
- [NEW FEATURE] Server-side transaction creation in `HasPaddleCheckout` trait when `checkout_url` is configured — passes `checkout.url` to Paddle API so emails/receipts link to the correct domain
- [NEW FEATURE] Install command auto-sets `PADDLE_CHECKOUT_URL` and `PADDLE_SANDBOX_CHECKOUT_URL` from `APP_URL`

### Improvements
- [IMPROVED] `paddleCheckoutOptions()` now branches into server-side (transactionId) or client-side (items) flow based on checkout URL config

## [1.4.0] — 2026-02-24

### Removals
- [IMPROVED] Removed ZeptoMail dependency — mail provider is now the app's concern, not the billing package
- [IMPROVED] Removed ZeptoMail installer section and `injectZeptoMailConfig()` method from install command

## [1.3.0] — 2026-02-23

### Features
- [NEW FEATURE] Added `metadata` JSON column to `customers` table for storing custom data (e.g., GitHub username)
- [NEW FEATURE] `StorePaddleEvent` now merges Paddle `customData` into customer `metadata` on each purchase

## [1.2.1] — 2026-02-23

### Fixes
- [BUG FIX] Replace `exists()` + `create()` with `firstOrCreate()` in `StorePaddleEvent` to eliminate race condition where simultaneous webhook deliveries could bypass deduplication and trigger duplicate emails

## [1.2.0] — 2026-02-23

### Improvements
- [IMPROVED] Refactored to build on top of Cashier's `customers` table (`paddle_id` + morphs) instead of a custom `paddle_customer_id` column
- [IMPROVED] Guest checkout convention: `billable_type = 'guest'`, `billable_id = 0` for non-authenticated purchases
- [NEW FEATURE] Package migration for `products`, `purchases`, `purchase_metadata` tables (previously lived in app)
- [IMPROVED] Installer now publishes Cashier migrations and checks for both paddle-billing migration files
- [IMPROVED] Installer validates credential prefixes with `envAskWithPrefix` re-prompt loop
- [IMPROVED] Code clarity: extracted `createPurchaseMetadata()`, inline model resolution, cleaner variable naming

## [1.1.0] — 2026-02-23

### Features
- [NEW FEATURE] `PurchaseCompleted` event fired after `transaction.completed` is processed — carry structured models + `customData` to app listeners
- [NEW FEATURE] `paddle-billing.models` config key — override `customer`, `product`, `purchase` model classes so the package is not coupled to `App\Models\*`
- [NEW FEATURE] ZeptoMail support in installer — prompts for From address, From name, API key; injects mailer into `config/mail.php` automatically
- [NEW FEATURE] `src/Events/PurchaseCompleted.php` — typed event with base Eloquent models for full namespace decoupling

### Improvements
- [IMPROVED] `StorePaddleEvent` resolves model classes from `config('paddle-billing.models')` instead of hardcoded `App\Models\*` imports
- [IMPROVED] `PurchaseCompleted` models typed as `Illuminate\Database\Eloquent\Model` — no app namespace leaking into the package
- [IMPROVED] Installer step numbering corrected; ZeptoMail and Pushover sections clearly separated
- [IMPROVED] PHPDoc added to all classes and non-trivial methods

### Dependencies
- [DEPENDENCIES] Added `brunocfalcao/laravel-zepto-mail-api-driver` as a package dependency

## [1.0.0] — 2026-02-01

- Initial release: config, migrations, `StorePaddleEvent`, `HasPaddleCheckout` trait, `paddle_price_id()` / `paddle_checkout_options()` helpers, `@paddleJs` / `@paddleInit` Blade directives, interactive installer
