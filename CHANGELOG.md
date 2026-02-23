# Changelog

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
