# cron-shim client

The site-side reporting client. It runs WordPress cron, reports the outcome to the hub, and is safe to
run on a schedule: **it always exits `0` and never changes your cron's behaviour, even when the hub is
unreachable** (failed reports are spooled and retried on the next run).

## Install

1. Copy `cron-shim.php` somewhere on the server, e.g. `/usr/local/bin/cron-shim.php`.
2. Create a config file, e.g. `/etc/cron-shim.env` (see `cron-shim.env.example`). Keep it `0600` — it
   holds the site token and signing secret.
3. Add a system cron entry (see `crontab.example`) to run it on the interval you configure in the hub.

For sites that have `DISABLE_WP_CRON` set (the recommended setup), point the client at the WordPress
install and let it invoke WP-CLI:

```sh
SHIM_CRON_COMMAND="cd /var/www/example.com && wp cron event run --due-now"
```

## Configuration

| Variable | Required | Default | Purpose |
|----------|----------|---------|---------|
| `SHIM_HUB_URL` | yes | — | Hub base URL, e.g. `https://cron.example.com` |
| `SHIM_SITE_UUID` | yes | — | Site UUID from the hub |
| `SHIM_TOKEN` | yes | — | Site bearer token from the hub |
| `SHIM_SECRET` | no | — | Signing secret; when set the report is HMAC-signed |
| `SHIM_TIMEOUT` | no | `10` | HTTP timeout in seconds |
| `SHIM_CRON_COMMAND` | no | `wp cron event run --due-now` | Command that runs the cron |
| `SHIM_SPOOL_DIR` | no | system temp | Where failed reports are buffered |
| `SHIM_LOG_FILE` | no | — | Local client log file |
| `SHIM_MAX_LOG_ENTRIES` | no | `100` | Max log lines sent per run |
| `SHIM_MAX_SPOOL` | no | `50` | Max buffered reports before oldest are dropped |

## Usage

```sh
php cron-shim.php --env-file=/etc/cron-shim.env
php cron-shim.php --dry-run          # print the payload as JSON, send nothing
```

The payload contract is documented in [`../doc/api-ingest.md`](../doc/api-ingest.md).
