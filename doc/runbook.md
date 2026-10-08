# Operations runbook

## Production configuration

Copy `.env.example` to `.env` and set at least:

```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://cron.example.com
APP_KEY=            # php artisan key:generate --show
CRONSHIM_FORCE_HTTPS=true   # when TLS is terminated by a proxy
DB_CONNECTION=mysql
DB_HOST=... DB_DATABASE=... DB_USERNAME=... DB_PASSWORD=...
QUEUE_CONNECTION=database
MAIL_MAILER=smtp            # or a transactional provider
CRONSHIM_ADMIN_EMAIL=you@example.com
CRONSHIM_ADMIN_PASSWORD=change-me
```

Then:

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan db:seed --class=AdminUserSeeder   # first admin only
```

Change the admin password after first login.

## Background processes

Two long-running processes are required:

| Process | Command | Purpose |
|---------|---------|---------|
| Queue worker | `php artisan queue:work --tries=3 --backoff=5` | sends notifications |
| Scheduler | `php artisan schedule:work` | health evaluation (every minute) + retention (daily) |

The scheduler can alternatively be driven by system cron:

```cron
* * * * * cd /var/www/cron-shim && php artisan schedule:run >> /dev/null 2>&1
```

Supervisor example (`/etc/supervisor/conf.d/cron-shim.conf`):

```ini
[program:cron-shim-queue]
command=php /var/www/cron-shim/artisan queue:work --tries=3 --backoff=5
autostart=true
autorestart=true
numprocs=1
user=www-data
stopwaitsecs=3600

[program:cron-shim-scheduler]
command=php /var/www/cron-shim/artisan schedule:work
autostart=true
autorestart=true
user=www-data
```

Run `php artisan queue:restart` after each deploy so workers pick up new code.

## TLS and reverse proxy

Terminate TLS at your proxy (nginx, Caddy, Cloudflare). The app trusts proxy headers
(`trustProxies(at: '*')`) and sets `X-Content-Type-Options`, `X-Frame-Options`,
`Referrer-Policy`, `Permissions-Policy`, and (over HTTPS) `Strict-Transport-Security`.

Set `CRONSHIM_FORCE_HTTPS=true` if generated URLs come out as `http://`.

## Backups

```bash
# database
mysqldump --single-transaction -u "$DB_USERNAME" -p "$DB_PASSWORD" "$DB_DATABASE" \
  | gzip > cron-shim-$(date +%F).sql.gz

# restore
gunzip -c cron-shim-YYYY-MM-DD.sql.gz | mysql -u "$DB_USERNAME" -p "$DB_DATABASE"
```

Only the database needs backing up (assets are rebuilt from source). Test restores periodically.

## Retention

`php artisan cronshim:prune` (scheduled daily) deletes:

- runs older than `CRONSHIM_RETENTION_RUNS_DAYS` (default 90)
- log entries older than `CRONSHIM_RETENTION_LOGS_DAYS` (default 30)
- notification logs older than `CRONSHIM_RETENTION_NOTIFICATIONS_DAYS` (default 90)

## Security checklist

- [ ] `APP_DEBUG=false`, `APP_ENV=production`
- [ ] HTTPS only; `CRONSHIM_FORCE_HTTPS=true`
- [ ] Session cookies secure (set `SESSION_SECURE_COOKIE=true`)
- [ ] `CRONSHIM_INGEST_REQUIRE_SIGNATURE=true` and each site given its signing secret
- [ ] Admin password changed from the seeded value
- [ ] `composer audit` clean
- [ ] Database backups scheduled and restore tested

## Troubleshooting

- **Sites show "silent" immediately** — expected until a client reports. Send a test run with
  `php cron-shim.php --dry-run` on the client, then a real run.
- **Notifications not arriving** — check `queue:work` is running and the Settings page's
  "Recent deliveries" list for `failed` entries.
- **`/up` fails** — the app or database is down; check the web and database logs.
