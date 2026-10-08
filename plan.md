# cron-shim hub — Implementation Plan

**Version:** 3
**Status:** 🚧 In Progress
**Type:** feature
**Last updated:** 2026-10-08

## Plan Contract

**Mission:** Build a Laravel web hub that catalogs cron-shim instances, receives each site's WordPress-cron run report over authenticated HTTP, and surfaces fleet-wide run history, logs, and health incidents with email/Slack alerting.

**Scope**

- **In scope**
  - Versioned **ingest API** for site-side reports (auth, optional HMAC signing, idempotency, rate limiting).
  - **Site catalog**: create/edit/disable sites, per-site ingest credentials, connect wizard, secret rotation.
  - **Run + log storage and browsing** across all sites (global and per-site, filterable, searchable).
  - **Health detection**: missed runs, failed runs, repeated failures, recovery.
  - **Incidents** with open/resolved lifecycle and acknowledge/resolve.
  - **Notifications** via email (SMTP) and Slack incoming webhook, with flap/throttle control.
  - **Fleet dashboard** overview of health.
  - Auth (single admin), dev environment, automated tests, and operator docs.
  - The **site-side reporting client** (`shim/`) that runs WP cron and phones home without ever breaking cron.
- **Out of scope (deferred — see [Deferred / Future](#deferred--future))**
  - Remote triggering of a site's cron from the hub.
  - Remote push of schedules/config to sites.
  - Multi-user teams, RBAC, per-site access control.
  - Public status pages, SMS/PagerDuty, Prometheus exporter.
  - Packaging the client as an installable WordPress plugin.

**Success Metrics** (deterministic — each provable by a command/exit code)

- `php artisan test` passes (full suite, exit 0).
- A valid signed report to `POST /api/v1/ingest` returns `202` and persists one `cron_runs` row plus its `cron_log_entries`.
- Unknown/disabled site or bad token → `401`/`403` and no rows written.
- Replaying an identical `run_uuid` creates **no** duplicate run row.
- A site whose `last_run_at` exceeds `interval + grace` produces an open `missed_run` incident.
- A `failed` run queues a notification; `Notification::fake()` / `Mail::fake()` assertions pass.
- `php artisan migrate:fresh --seed` exits 0 and seeds a demo fleet.
- Behind TLS, `GET /up` returns 200 and the dashboard requires auth (`/` → 302 to `/login`).

**Approach / Architectural Fit**

- **Stack:** Laravel 13 (PHP 8.5), Blade + Livewire 4 + Tailwind 4 for the UI, PHPUnit for tests, MySQL in production with SQLite for the test suite.
- **Async:** Laravel scheduler drives health evaluation; Laravel queue (database driver first, Redis optional) drives notifications. No external services required to boot.
- **HTTP:** single stateless `POST /api/v1/ingest` endpoint; browser UI is session-authenticated.
- **Shape:** ingest service normalizes the payload, the model layer stores it, a health evaluator turns raw runs into incidents, and a notifier fans out to channels — each concern isolated so it is independently testable.
- Full details in [Architecture](#architecture-overview), [Data Model](#data-model), and [Ingest Contract](#ingest-contract) below.

**Assumptions** — confirm or veto these; each is cheap to change but shapes the phases.

1. **Repo placement.** The hub is built in *this* repo at the root (standard Laravel layout) and the site-side reporting client ships under `shim/`, so the API contract and its client version together. *(Alternative: separate `cron-shim-hub` repo.)*
2. **UI framework.** Livewire 4 + Blade + Tailwind 4 (server-rendered, no SPA build) rather than Inertia/React.
3. **Database.** MySQL 8 in production; SQLite in-memory for tests.
4. **Queue.** Laravel `database` queue driver initially; Redis documented as an upgrade.
5. **Auth.** A single admin account via Laravel's starter auth; user/roles tables exist but team features are out of scope.
6. **Notifications.** Email (SMTP) and Slack incoming webhook are the v1 channels.
7. **TLS.** HTTPS is terminated by a reverse proxy; the app sets secure cookies and trusts the proxy.

**References**

- `agent.md` — repo conventions for agents.
- `development-workflow` skill — mandatory commit/push and workflow rules.
- `plan-generation-skill` — phase/validation format used here.
- `laravel-specialist` skill — Laravel build conventions.
- Security skills: `input-sanitization-validation`, `rest-api-security`, `output-escaping`, `nonces-csrf-protection`, `secrets-credentials-management`, `http-api-ssrf-prevention`, `sql-injection-prevention`, `capability-permission-checks`, `security-headers-csp`.
- `docker-control-sh` skill — a `control.sh` for the compose stack.

---

## Architecture Overview

```
 WordPress sites                 Central hub (Laravel)                    Users
 ┌────────────────┐   HTTPS POST  ┌────────────────────────────┐   HTTPS  ┌──────────┐
 │ system cron    │  /api/v1/     │ Ingest API                  │  ──────► │ Browser  │
 │  └ cron-shim   │ ────────────► │  ├ auth: token + HMAC       │          │ (Blade + │
 │    reporting   │  signed JSON  │  ├ validate + idempotency   │          │ Livewire)│
 │    client      │ ◄──────────── │  └ persist run + logs       │          └──────────┘
 └────────────────┘    202        │                             │
   spool + retry                 │ Scheduler → Health evaluation│   ┌──────────────┐
                                 │ Queue → Notifications ───────┼─► │ Email / Slack│
                                 │ DB: sites/runs/logs/incidents│   └──────────────┘
                                 └────────────────────────────┘
```

**Request flow (happy path):** site cron runs → reporting client captures outcome → signs + POSTs → ingest auth resolves the site → payload validated → run + logs persisted idempotently → site `last_run_at`/`last_status` updated → health evaluator later reconciles incidents → notifier sends on state change.

## Data Model

| Table | Key columns | Notes |
|-------|-------------|-------|
| `sites` | `uuid` (unique), `name`, `url`, `environment`, `timezone`, `expected_interval_minutes`, `grace_minutes`, `is_active`, `ingest_token_hash`, `last_run_at`, `last_status`, `last_seen_ip`, `notes` | The catalog. Secret stored hashed only. |
| `cron_runs` | `site_id`, `run_uuid` (unique per site), `started_at`, `finished_at`, `duration_ms`, `status`, `exit_code`, `jobs_run`, `jobs_failed`, `summary`, `source_ip`, `meta` (json) | Index `(site_id, started_at)`, `(status, started_at)`. |
| `cron_log_entries` | `cron_run_id`, `site_id`, `level`, `message`, `context` (json), `logged_at` | Index `(site_id, logged_at)`, `(level, logged_at)`. |
| `incidents` | `site_id`, `type`, `status`, `opened_at`, `resolved_at`, `details` (json), `last_notified_at`, `notify_count` | Index `(status, opened_at)`; dedupe open incidents. |
| `notification_channels` | `type` (`email`/`slack`), `name`, `config` (encrypted json), `is_active` | Config encrypted at rest. |
| `notification_logs` | `incident_id`, `channel_id`, `subject`, `status`, `error`, `sent_at` | Delivery audit trail. |
| `users` | standard Laravel auth | Single-admin v1. |

Enums: run `status` = `success` \| `warning` \| `failed`; incident `type` = `missed_run` \| `run_failed` \| `repeated_failure` \| `recovered`; incident `status` = `open` \| `resolved`; log `level` = `debug` \| `info` \| `notice` \| `warning` \| `error` \| `critical`.

## Ingest Contract

`POST {HUB_URL}/api/v1/ingest` — JSON, HTTPS, stateless.

Headers:
- `Authorization: Bearer <site token>`
- `X-Shim-Site: <site uuid>`
- `X-Shim-Signature: sha256=<hmac of raw body with site secret>` (recommended)
- `Content-Type: application/json`

Body:
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

Responses: `202 {"status":"accepted","run_uuid":"..."}`; `200 {"status":"duplicate"}` when already stored; `401`/`403` auth; `422` validation. Requests are size-limited and rate-limited per site.

---

## Phases

### Phase 1: Scaffold the Hub & Local Dev Environment

**Goal:** A runnable Laravel hub skeleton with authentication, base layout, and a reproducible dev environment.

#### Tasks
- [x] Create the Laravel app at the repo root (Laravel 13, PHP 8.5); commit the skeleton.
- [x] Add `.env.example` covering app URL, DB, queue, mail, and hub settings — **no secrets committed**.
- [x] Add session auth (`LoginController` with rate limiting) and protect all app routes behind `auth` (hand-rolled; no Breeze dependency).
- [x] Add Tailwind 4 + a base layout component with nav: Dashboard, Sites, Runs, Logs, Incidents, Settings.
- [x] Add a Docker Compose dev stack (app, MySQL 8.4, queue worker, scheduler) with a multi-stage `Dockerfile`.
- [x] Add CI running `php artisan test` and Pint (`.github/workflows/ci.yml`).
- [x] Create `doc/` and a README quickstart.
- [x] **Validation:** `php artisan migrate --seed` exits 0 on a fresh DB; `php artisan test --compact` passes (9 tests); `docker compose config -q` is valid.

#### Expected outcomes
- `php artisan test` passes on the scaffolding.
- Unauthenticated `GET /` returns 302; after login the dashboard returns 200.

**Commit:** `b27e1b3` — `feat: scaffold Laravel hub with auth, layout, docker stack, and CI`

### Phase 2: Domain Model & Migrations

**Goal:** Persist sites, runs, log entries, incidents, channels, and notification logs with correct constraints and indexes.

#### Tasks
- [ ] Create migrations for every table in [Data Model](#data-model) with FKs, unique constraints, and indexes.
- [ ] Create Eloquent models with `$casts`, `$fillable`/guarded, enums (PHP backed enums), and relationships.
- [ ] Create model factories for every model.
- [ ] Add a `DemoSeeder` that seeds a small fleet (sites, runs, logs, one incident).
- [ ] **Validation:** `php artisan migrate:fresh --seed` exits 0 and every model has a working factory.

#### Expected outcomes
- `php artisan db:seed --class=DemoSeeder` populates a browsable demo dataset.

### Phase 3: Ingest API

**Goal:** A secure, idempotent endpoint that accepts a signed run report and stores the run and its logs.

#### Tasks
- [ ] Register `POST /api/v1/ingest` with stateless middleware and a dedicated rate limiter.
- [ ] Implement site auth: resolve by `X-Shim-Site`, verify Bearer token against `ingest_token_hash` (Hash::check); reject unknown/inactive with 401/403 and log rejections.
- [ ] Implement optional HMAC verification of `X-Shim-Signature` over the raw body using the per-site secret (constant-time compare).
- [ ] Add a `FormRequest` validating the payload (required fields, types, enum statuses, max log count/size).
- [ ] Enforce idempotency on `run_uuid`; a duplicate returns `200 {"status":"duplicate"}` with no new row.
- [ ] Persist `CronRun` + batch `CronLogEntry`; update site `last_run_at`, `last_status`, `last_seen_ip`.
- [ ] Return `202 {"status":"accepted"}`.
- [ ] Write feature tests: valid accepted; bad token 401; unknown site 404; disabled site 403; duplicate idempotent; oversize 422.
- [ ] Document the contract in `doc/api-ingest.md`.
- [ ] **Validation:** `php artisan test --filter=Ingest` passes; a signed `curl` fixture returns 202 and increments the run count; replaying the same `run_uuid` adds no row.

### Phase 4: Site-Side Reporting Client (`shim/`)

**Goal:** The site script runs WP cron and reports to the hub without ever altering cron's own exit code.

#### Tasks
- [ ] Add `shim/` client configured by an env file (`SHIM_HUB_URL`, `SHIM_SITE_UUID`, `SHIM_TOKEN`/`SHIM_SECRET`, `SHIM_TIMEOUT`).
- [ ] Run WP cron (`wp cron event run --due-now` or `wp-cron.php`), capturing stdout/stderr, exit code, and timing.
- [ ] Build the JSON payload per [Ingest Contract](#ingest-contract) including `meta` (WP/PHP/shim versions).
- [ ] Sign (HMAC) and POST over HTTPS with a short timeout; **never** block or change the cron exit code.
- [ ] Add a bounded local spool: on failure, persist the payload; flush pending payloads at the start of the next run.
- [ ] Write the client's own structured log for diagnostics.
- [ ] `shim/README.md`: install, configure, and example crontab entry.
- [ ] Contract test: client output validates against the same JSON schema the API enforces.
- [ ] **Validation:** with the hub unreachable the client still exits 0; spooled payload is delivered on the next run after recovery; `shellcheck` (and the schema test) passes.

### Phase 5: Site Catalog & Management UI

**Goal:** Create and manage sites and their ingest credentials from the UI.

#### Tasks
- [ ] Sites CRUD (Livewire) with full validation and authorization policies.
- [ ] "Connect a new site" wizard: generate site UUID + secret, show once, provide a copy-ready env snippet and crontab line.
- [ ] Secret rotation: regenerate and invalidate the previous secret.
- [ ] Enable/disable and edit interval, grace, and timezone.
- [ ] Sites list with search, filter, sort, and a health badge (healthy/degraded/silent).
- [ ] Feature tests for CRUD, rotation, policy enforcement, and validation.
- [ ] **Validation:** `php artisan test --filter=SiteManagement` passes; a newly created site reports successfully, and disabling it causes ingest to return 403 (test).

### Phase 6: Runs & Logs Browsing UI

**Goal:** Browse all runs and logs across the fleet.

#### Tasks
- [ ] Global Runs index: filters by site, status, date range; pagination; row → run detail.
- [ ] Run detail: timing, exit code, jobs, `meta`, and color-coded log entries.
- [ ] Global Logs stream: filters by site/level/date plus free-text search; pagination.
- [ ] Embed per-site run/log views on site detail.
- [ ] Add CSV export for the current filtered set.
- [ ] Eliminate N+1 (eager loading) and add any needed indexes.
- [ ] Feature tests for filters, search, and detail rendering.
- [ ] **Validation:** `php artisan test --filter=RunBrowsing` passes; the runs index test asserts a bounded query count; the logs filter returns only matching level/site.

### Phase 7: Health Detection & Incidents

**Goal:** Detect missing and failing runs and track them as incidents.

#### Tasks
- [ ] `EvaluateSiteHealth` command: for each active site, if `now > last_run_at + interval + grace` open a `missed_run` incident.
- [ ] On ingest: open `run_failed` for a failed run; escalate to `repeated_failure` after a configurable consecutive-failure threshold.
- [ ] Auto-resolve open incidents on the next successful run, recording the resolution time (`recovered`).
- [ ] Deduplicate: never open a second open incident of the same type for a site.
- [ ] Schedule the command (every minute) with `schedule:run` running in the compose stack.
- [ ] Incidents UI: open/resolved lists with filters and manual acknowledge/resolve.
- [ ] Tests using time travel (`Carbon::setTestNow`).
- [ ] **Validation:** `php artisan test --filter=Health` passes — stale site opens an incident, recovery resolves it, repeated failures escalate; `php artisan schedule:list` shows the evaluation command.

### Phase 8: Notifications

**Goal:** Notify on open/escalate/recover over email and Slack, with throttling.

#### Tasks
- [ ] NotificationChannel UI/model: add email recipients or a Slack webhook (encrypted config).
- [ ] Laravel Notifications `IncidentOpened`, `IncidentEscalated`, `IncidentRecovered` implementing mail and Slack channels.
- [ ] Queue notifications (`ShouldQueue`) with retry/backoff; record every send in `notification_logs`.
- [ ] Throttle per incident (minimum interval) and suppress notification storms.
- [ ] "Send test notification" action per channel.
- [ ] Keep secrets/PII out of notification bodies.
- [ ] Tests with `Notification::fake()` / `Mail::fake()` and a `queue:work --once` run.
- [ ] **Validation:** `php artisan test --filter=Notifications` passes; the test-notification action queues a job; a worker processes it.

### Phase 9: Fleet Dashboard

**Goal:** One screen showing the health of the whole fleet.

#### Tasks
- [ ] Widgets: counts of healthy/degraded/silent, open incidents, failures and runs in the last 24h, slowest runs.
- [ ] Per-site status cards: name, last run, next expected run, status, recent-outcome sparkline.
- [ ] Livewire polling refresh (30–60s).
- [ ] Empty state / onboarding hints when no sites exist.
- [ ] Responsive layout.
- [ ] **Validation:** `php artisan test --filter=Dashboard` passes with correct aggregate counts; `curl -s <dashboard> | grep -c 'site-card'` equals the active-site count.

### Phase 10: Hardening, Retention & Operations

**Goal:** A production-ready posture.

#### Tasks
- [ ] Enforce HTTPS/secure cookies/HSTS behind the proxy; configure trusted proxies and security headers.
- [ ] Retention: scheduled pruning of runs/logs/notifications older than a configurable window (index/partition notes in docs).
- [ ] Rate limiting on API and login; failed-ingest logging; `/up` health endpoint.
- [ ] Backups (MySQL dump) + restore runbook; Supervisor/systemd docs for queue worker and scheduler.
- [ ] Security pass: authorization, mass-assignment, output escaping, encrypted secret storage, `composer audit`.
- [ ] Finalize README + `doc/` runbooks; update `agent.md` with the shipped layout.
- [ ] **Validation:** full `php artisan test` passes; `composer audit` reports no unaddressed advisories; the retention job prunes time-travelled old rows; `GET /up` returns 200.

### Deferred / Future

Not part of this plan; recorded so scope stays honest.

- Remote triggering of a site's cron from the hub.
- Remote config/schedule push to sites.
- Multi-user teams, RBAC, per-site access control.
- Public status page, SMS/PagerDuty, Prometheus exporter.
- WordPress plugin packaging of the reporting client.

## Open Questions

1. **Repo placement** — hub in this repo (assumed) vs. a dedicated `cron-shim-hub` repo.
2. **UI framework** — Livewire (assumed) vs. Inertia/React.
3. **Database engine** — MySQL (assumed) vs. PostgreSQL.
4. **Auth** — local single admin (assumed) vs. SSO.
5. **Channels** — beyond email + Slack? (e.g. Discord, webhook).
6. **Retention defaults** — e.g. 30 days of logs, 90 days of runs.
7. **Failure threshold** — consecutive failed runs before escalation (default 3).
