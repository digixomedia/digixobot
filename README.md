# DigiXO Telegram Store

Standalone Telegram storefront and private Filament admin panel for DigiXO.

## Included

- Telegram category, product, plan, deal, search, wallet, order, account, and support flows
- Atomic wallet purchases with idempotency, stock locking, and an immutable ledger
- Safe full-order refunds that restore wallet balance and stock exactly once
- Private admin dashboard for catalog, inventory, customers, orders, wallet history, support, deals, broadcasts, settings, and audit logs
- Database-backed broadcast queue and production scheduler
- Telegram webhook authentication and duplicate-update protection

## Production paths

- Admin: `https://bot.leadbotcloud.com/admin`
- Webhook: `https://bot.leadbotcloud.com/telegram/webhook`
- Application: `/home/u657439012/digixobot`

## Deployment

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan optimize
php artisan test
```

Hostinger must run this cron command every minute:

```bash
cd /home/u657439012/digixobot && php artisan schedule:run
```

Never commit `.env`. Configure `APP_KEY`, MySQL credentials, `TELEGRAM_BOT_TOKEN`, and `TELEGRAM_WEBHOOK_SECRET` only in the production environment.
