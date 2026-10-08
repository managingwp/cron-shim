# cron-shim hub

Central management hub for [cron-shim](https://github.com/managingwp/cron-shim) instances.

WordPress sites run their cron through the cron-shim script. Each site reports the outcome of every
run to this hub over an authenticated HTTP call. The hub catalogs every site, stores run history and
logs, detects missed or failing runs, and alerts via email or Slack.

## Features

- **Ingest API** — authenticated, idempotent `POST /api/v1/ingest` for site run reports.
- **Site catalog** — register sites, issue per-site credentials, rotate secrets, enable/disable.
- **Run & log browsing** — fleet-wide and per-site run history and log stream with filters and search.
- **Health monitoring** — detects missed runs, failed runs, and repeated failures; auto-resolves on recovery.
- **Incidents** — open/resolved lifecycle with acknowledge and manual resolve.
- **Notifications** — email (SMTP) and Slack incoming webhook, with flap throttling.
- **Dashboard** — live fleet health overview.

## Requirements

- PHP 8.3+ (built and tested on 8.5)
- Composer 2
- Node 22 (for asset builds)
- MySQL 8 for production; SQLite is used for local development and tests

## Quick start

```bash
cp .env.example .env
php artisan key:generate
php artisan migrate --seed      # creates the bootstrap admin from CRONSHIM_ADMIN_EMAIL/PASSWORD
npm install && npm run build
php artisan serve
```

Log in at http://localhost:8000 with the seeded administrator, then change the password.

### Docker

```bash
docker compose up -d --build
docker compose exec app php artisan migrate --seed
```

The app is served on http://localhost:8000 with MySQL, a queue worker, and the scheduler.

## Documentation

- [`plan.md`](./plan.md) — implementation roadmap.
- [`agent.md`](./agent.md) — contributor/agent guide and project conventions.
- [`doc/api-ingest.md`](./doc/api-ingest.md) — ingest API contract.
- [`doc/development.md`](./doc/development.md) — local development, testing, and operations.

## License

MIT
