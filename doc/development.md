# Development

## Requirements

- PHP 8.3+ (developed on 8.5) with `pdo_sqlite`, `pdo_mysql`, `mbstring`, `dom`, `curl`, `zip`, `gd`, `intl`, `bcmath`
- Composer 2
- Node 22 + npm

## Setup

```bash
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
npm install
npm run build
php artisan serve
```

For live asset reloading during development run `npm run dev` in a second terminal (or `composer run dev`).

## Testing

The suite runs on SQLite in-memory (`phpunit.xml`).

```bash
php artisan test --compact          # full suite
php artisan test --filter=Ingest    # focused run
vendor/bin/pint                     # fix code style
vendor/bin/pint --test              # check code style
composer audit                      # dependency advisories
```

Use factories for fixtures and `Carbon::setTestNow()` for time-dependent tests. Notifications are
asserted with `Notification::fake()` / `Mail::fake()`.

## Background workers

The hub relies on two long-running processes in production:

```bash
php artisan queue:work          # sends notifications, other async work
php artisan schedule:work       # drives health evaluation and retention
```

`docker compose` runs both as separate services.

## Configuration

All tunables live in `config/cronshim.php` and are overridable via environment variables — see
`.env.example`. Key groups: `CRONSHIM_ADMIN_*`, `CRONSHIM_INGEST_*`, `CRONSHIM_FAILURE_THRESHOLD`,
`CRONSHIM_NOTIFY_MIN_INTERVAL`, and `CRONSHIM_RETENTION_*`.
