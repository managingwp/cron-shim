# agent.md — cron-shim hub

Repo guide for AI agents (and humans) working on `cron-shim`. Read this before changing code.
Laravel Boost's framework guidelines live in [`AGENTS.md`](./AGENTS.md); this file is the project guide.

## What this repo is

`cron-shim` is evolving into a **central hub for WordPress cron**. WordPress sites run `wp-cron.php`
through a site-side script; that script reports each run to this hub over an authenticated HTTP call.
The hub catalogs every site, stores run history and logs, detects missed/failing runs, and alerts.

The build plan lives in [`plan.md`](./plan.md). This file describes how to work in the repo, not what to build.

Two parts:

1. **Hub** — the Laravel web application at the repo root (dashboard, ingest API, storage, alerting).
2. **Client** — the site-side reporting script under `shim/` that runs WP cron and phones home.

## Current state

The hub is **implemented** — see [`plan.md`](./plan.md) for the phase history and status. The Laravel
application lives at the repo root; the site-side client lives in `shim/`.

## Mandatory workflow

The `development-workflow` skill is the source of truth for workflow. It applies to every session.
Key rules, restated so they are not missed:

- **Check repo sync before working.** Run `git fetch --dry-run` and `git status -sb`; if not synced, ask
  the user before pulling.
- **Commit and push after every change.** Conventional commits (`feat:`, `fix:`, `docs:`, `refactor:`, …).
  Print the commit message when done.
- **Stage explicit paths. Never `git add -A` / `git add .`.** Parallel sessions may share the working dir.
  Run `git status --porcelain` before committing.
- **One logical change per commit.** Do not batch unrelated changes.
- **Docs live with code.** Update the relevant `doc/` file in the same commit. Search `doc/` before
  creating anything new to avoid duplicates.
- **Do not create test scripts in the main codebase.**
- **Ask before connecting to remote servers** — analyze pasted output instead.
- **Suggest how to test** after completing code changes.

## Project conventions

### Committing plan work

- Editing `plan.md` is **documentation work**. A request to add/modify a phase means edit `plan.md` and
  commit with `docs:`, then stop — do not start implementing unless explicitly told to execute.
- Executing the plan follows the `plan-generation-skill` execution workflow: work phase by phase, tick
  checkboxes as tasks complete, and **commit after each phase passes its validation** before moving on.
- If the plan is later moved into a `plans/` directory as a PGF, run `pgf status` first and follow the
  PGF conventions from the `plan-generation-skill`.

### Laravel

- Follow the `laravel-specialist` skill for Eloquent, controllers, queues, and tests.
- **Validation:** every request uses a `FormRequest`; never trust `$request->all()` blindly.
- **Authorization:** gate actions with policies/`authorize()`; the ingest API authenticates per-site.
- **Enums:** use PHP backed enums for run status, incident type/status, and log level; cast them on models.
- **Queries:** eager-load relations on list screens to avoid N+1; keep filtered columns indexed.
- **Services/actions:** put ingest normalization, health evaluation, and notification fan-out in dedicated
  service classes so they are unit-testable, not in controllers.
- **Blade:** rely on auto-escaping; never use `{!! !!}` on untrusted data.

### Security

Load and apply the relevant skills before writing the surface:

- Ingest API → `rest-api-security`, `input-sanitization-validation`, `http-api-ssrf-prevention`.
- Tokens/secrets → `secrets-credentials-management` (hash tokens with `Hash`, encrypt channel config,
  never commit secrets or print them in logs).
- Settings/forms → `settings-options-security`, `nonces-csrf-protection`, `output-escaping`.
- SQL → `sql-injection-prevention` (use bindings; no string-built queries).
- Headers/TLS → `security-headers-csp`.
- Always: HTTPS only, constant-time secret comparison, rate limiting, and size limits on ingest.

### The reporting client (`shim/`)

- Must **never** change or block the cron exit code, even when the hub is down.
- Buffer failed reports in a bounded local spool and flush on the next run.
- Send the minimum: run outcome, timing, exit code, job counts, and log lines. No PII.
- The client and the API share one JSON contract (`doc/api-ingest.md`); a contract test enforces it.

## Repo layout (target)

```
.
├── app/                      # Laravel hub (Laravel 13 / PHP 8.5)
│   ├── Console/Commands/     # EvaluateSiteHealth, PruneOldData
│   ├── Enums/                # RunStatus, IncidentType, IncidentStatus, LogLevel, NotificationChannelType
│   ├── Http/
│   │   ├── Controllers/      # Site, Run, Log, Incident, Settings, Api\Ingest
│   │   ├── Middleware/       # AuthenticateSite (ingest), SecurityHeaders
│   │   └── Requests/         # IngestReportRequest, Store/UpdateSiteRequest
│   ├── Jobs/                 # SendIncidentNotification
│   ├── Livewire/             # Dashboard (polling)
│   ├── Models/               # Site, CronRun, CronLogEntry, Incident, NotificationChannel, NotificationLog
│   ├── Policies/             # SitePolicy
│   └── Services/             # IngestService, HealthEvaluator, IncidentNotifier, NotificationSender
├── config/cronshim.php       # hub tunables
├── database/                 # migrations, factories, seeders (Admin, Demo)
├── doc/                      # api-ingest.md, development.md, runbook.md
├── resources/views/          # Blade + Tailwind 4 (layouts/, sites/, runs/, logs/, incidents/, settings/, livewire/)
├── routes/                   # web.php, api.php, console.php (schedule)
├── shim/                     # site-side client (cron-shim.php + docs)
├── tests/                    # PHPUnit feature + unit tests
├── docker-compose.yml, Dockerfile
├── plan.md                   # roadmap (implemented)
└── agent.md                  # this file
```

## Local development

```bash
# environment
cp .env.example .env
php artisan key:generate

# database + app
php artisan migrate --seed
php artisan serve

# background workers (separate terminals, or via docker compose)
php artisan queue:work
php artisan schedule:work

# full stack
docker compose up -d
```

## Testing

```bash
php artisan test                 # full suite (PHPUnit)
php artisan test --filter=Ingest # focused
composer audit                   # dependency advisories
```

- Tests use SQLite in-memory and `RefreshDatabase`.
- Use `factories` for fixtures; use `Carbon::setTestNow()` for time-based health tests.
- Use `Notification::fake()` / `Mail::fake()` instead of sending real mail.

## Skills to load

`development-workflow` (mandatory), `laravel-specialist`, `plan-generation-skill`,
`docker-control-sh`, and the security skills listed above. Load the WordPress skills only if touching
site-side WordPress integration.

## Definition of done

A change is done when:

1. The code is implemented and matches the phase in `plan.md`.
2. The phase's **Validation** check passes (from command output, not eyeballing a screen).
3. Tests pass: `php artisan test`.
4. Docs in `doc/` are updated in the same commit.
5. The change is committed with a conventional message and pushed, staging explicit paths only.

## Do not

- Commit secrets, tokens, or real `.env` files.
- Use `git add -A` / `git add .`.
- Implement `plan.md` tasks while asked only to edit the plan.
- Let the reporting client break a site's cron run.
- Create scratch/test scripts inside the application tree.
