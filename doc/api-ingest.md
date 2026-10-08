# Ingest API

The site-side reporting client posts the outcome of every WordPress cron run to:

```
POST {HUB_URL}/api/v1/ingest
```

It is stateless, JSON, and HTTPS-only in production.

## Authentication

Every request must carry:

| Header | Value |
|--------|-------|
| `Authorization` | `Bearer <site token>` |
| `X-Shim-Site` | The site's UUID |
| `X-Shim-Signature` | `sha256=<hex HMAC>` — optional by default, required when `CRONSHIM_INGEST_REQUIRE_SIGNATURE=true` |
| `Content-Type` | `application/json` |

- The **token** is verified against a hash stored on the site (`ingest_token_hash`).
- The **signature** is `hash_hmac('sha256', <raw request body>, <site signing secret>)`. The secret is
  shown once when the site is created and stored encrypted at rest.
- Unknown sites, bad tokens, and bad signatures return `401`. Disabled sites return `403`.

## Request body

```json
{
  "site_uuid": "0b0e...",
  "run": {
    "run_uuid": "9f1c...",
    "started_at": "2026-10-08T12:00:00Z",
    "finished_at": "2026-10-08T12:00:03Z",
    "duration_ms": 3120,
    "status": "success",
    "exit_code": 0,
    "jobs_run": 12,
    "jobs_failed": 0,
    "summary": "12 due events run"
  },
  "logs": [
    { "level": "info", "message": "Running due cron events", "logged_at": "2026-10-08T12:00:00Z", "context": {} }
  ],
  "meta": { "wp_version": "6.7", "php_version": "8.3", "shim_version": "1.0.0" }
}
```

### Field rules

- `site_uuid` must match the authenticated site.
- `run.run_uuid` must be a UUID and is **idempotent**: re-posting the same `run_uuid` is safe and creates
  no duplicate run.
- `run.status` ∈ `success`, `warning`, `failed`.
- `logs[].level` ∈ `debug`, `info`, `notice`, `warning`, `error`, `critical`.
- `logs` is capped by `CRONSHIM_INGEST_MAX_LOG_ENTRIES` (default 200) and each message by
  `CRONSHIM_INGEST_MAX_MESSAGE_LENGTH` (default 5000).
- The whole body is capped by `CRONSHIM_INGEST_MAX_BODY_KB` (default 512).

## Responses

| Status | Meaning |
|--------|---------|
| `202` | Accepted — `{"status": "accepted", "run_uuid": "..."}` |
| `200` | Duplicate — `{"status": "duplicate", "run_uuid": "..."}` |
| `401` | Missing/invalid credentials or signature |
| `403` | Site disabled |
| `413` | Payload too large |
| `422` | Validation error |
| `429` | Rate limited (`throttle:ingest`, keyed by site then IP) |

On success the site's `last_run_at`, `last_status`, and `last_seen_ip` are updated.
