# Pixelforge Studio

An image-generation app on top of **Pixelforge**, the simulated provider from the PromptEdit take-home. Users sign in, write a prompt, see what it will cost, and pay Pixelforge's cost plus 25%, recorded to the nano-dollar.

**Live:** https://pixelforge.heptalex.ro · **Admin:** https://pixelforge.heptalex.ro/admin (a reviewer login is sent with the application)

Laravel 13 · Inertia 3 + Vue 3 · Tailwind 4 · Three.js (landing only, lazy) · MoonShine 4 (admin) · SQLite

---

## What it does

| | |
|---|---|
| **Generate** | Prompt, size, quality, up to 4 reference URLs. A live min / likely / max price as you type. |
| **History** | Every generation with live status (waiting → queued → processing → succeeded/failed), estimate vs actual charge, token usage, the image. |
| **Credits** (stretch) | Balance, "Buy credits" packs (fake purchase), full ledger. Generating is blocked when you can't afford the maximum. New accounts get $2. |
| **Admin report** (stretch) | Per UTC day and per user: what users were charged, what Pixelforge charged us, profit. Next to it: Pixelforge's own `GET /v1/usage`, with a ✓/✗ match per day. |

## Money: how every cost is exact

- **Integer nano-USD everywhere** (`BIGINT`, 1 USD = 10⁹). Pixelforge's costs have 9 decimals, so every provider amount fits exactly. No floats in PHP or JS (the browser uses `BigInt`).
- **Provider cost** = Σ tokens × rate, using the **rates on the generation object** (fixed by Pixelforge when it was created), not today's rates.
- **Charge** = cost × 1.25, **rounded up** to the next nano-dollar (at most 1 nano-USD in our favour; never undercharge).
- **Estimate** uses the live `GET /v1/pricing` rules: text tokens ±15%, reasoning range by quality, exact output and image tokens. The browser runs the same integer maths as the server, so the shown max is the held amount.
- **Hold → settle / refund.** On submit, the maximum possible charge is held. On success, the real charge is kept and the rest released; on failure, everything is refunded. **Failed generations cost nothing.**
- **Never twice.** The ledger has `UNIQUE(generation_id, type)`. A duplicated webhook, a replay, a poll and a webhook racing each other: whichever comes second hits the constraint and changes nothing.

Tests reproduce all three worked examples in the Pixelforge docs exactly (`0.373875000`, `0.305510000`, `0.169080000`).

### Verified against the real Pixelforge

| Run | What was checked | Result |
|---|---|---|
| Local, development key | `[succeed]`, reference image, `[fail]`, `[blocked]`, `[overloaded]`, `[slow]` | Every charge inside its estimate; failures free; overloaded retried 6× on `Retry-After`, then refunded; `GET /v1/usage` = our records (4 generations, `$0.503107500`) |
| Production, deployed key | `[duplicate]` + succeed / fail, plus a normal one, finished by **webhooks only** | Each duplicated event stored and applied once; one settle/refund per generation; `GET /v1/usage` = our records (2 generations, `$0.090807500`) |

## Robustness: how it copes with Pixelforge

```
 browser ──POST──▶ GenerationService ──(hold, store "pending")──▶ DB
                                │
                                └─▶ PumpGenerationQueue ──POST /v1/generations──▶ Pixelforge
                                      ≤3 in flight, ~55 req/min,          │
                                      oldest first, backoff               │ webhooks (any order, ≥1×)
                                                                          ▼
 ReconcileGenerations (poll if quiet 3 min) ──▶ GenerationUpdater ◀── /webhooks/pixelforge
                                               (the only place status & money change)
```

- **Accept fast, send at Pixelforge's pace.** A request is priced, held and stored locally in milliseconds. One *pump* job hands out Pixelforge's 3 slots oldest-first and is woken when a request arrives or a generation finishes. A burst of thousands just waits as `pending`, with no per-request job retrying in a loop.
- **Limits.** Own per-minute budget under 60 (shared with polling). `429` / `503` → retry no sooner than `Retry-After`, exponential backoff with jitter, give up after 6 tries and refund. `daily_limit_exceeded`, `content_policy_violation`, validation errors → fail immediately, free, with a clear message.
- **Idempotency.** The browser sends a UUID per form, so a double click makes one generation. Our ULID is the `Idempotency-Key` to Pixelforge and `metadata.generation_id`, so retried POSTs never create a second generation.
- **Webhooks.** HMAC-SHA256 over `t.raw_body`, constant-time compare, 5-minute replay window, verified before JSON parsing. Stored with `UNIQUE(event_id)`, answered `204` straight away, processed in a queued job. The route sits outside the `web` group (no session, no CSRF).
- **Out of order / early.** Status only moves forward (`pending < queued < processing < succeeded|failed`); a late `processing` after `succeeded` is ignored. A webhook that arrives before our POST returned is matched by `metadata.generation_id`.
- **Lost webhooks.** Anything in flight but quiet for 3 minutes is polled with `GET /v1/generations/{id}` through the same updater.
- **Images** are copied to our storage before Pixelforge's 24h URLs expire.
- **Everything is logged:** every outbound call in `api_calls` (status, error code, Retry-After, remaining quota, duration) with bodies in `api_logs` (Authorization is never stored); every webhook in `webhook_events` with duplicate counts.

## Users and admin

- One `users` table for the app and MoonShine (same `web` guard). Types live in a **`user_types` table**: `user` (10), `admin` (50), `super_admin` (100). Checks compare levels, so a new type (e.g. `support` at 30) works without code changes.
- Admins see the report and read-only money/audit tables; **super admins** also manage user types, users' types and API keys.
- Pixelforge **API keys are stored encrypted** in `api_keys` (one active key per environment). Secrets are write-only in the admin; only the last 4 characters are ever shown.

## Run it locally

```bash
git clone <this repo> pixelforge && cd pixelforge
composer install
npm install
cp .env.example .env && php artisan key:generate
touch database/database.sqlite && php artisan migrate

# Your Pixelforge key for "local" (asked with hidden input, stored encrypted):
php artisan pixelforge:key

# Make yourself super admin after registering:
php artisan user:type you@example.com super_admin

composer run dev          # app + queue worker + vite
php artisan schedule:work # pump/reconcile safety nets, usage sync
```

Webhooks need a public URL: run a tunnel (e.g. `cloudflared tunnel --url http://localhost:8000`) and set `https://<tunnel>/webhooks/pixelforge` on your **local** key in the Pixelforge dashboard.

### Try the failure modes
Use the keyword chips under the prompt: `[fail]`, `[slow]`, `[duplicate]`, `[overloaded]`, `[blocked]`, `[succeed]`.

### Tests
```bash
php vendor/bin/phpunit   # (php artisan test also works on a standard PHP install)
```
Covers pricing maths, the whole flow with a faked Pixelforge (limits, retries, refunds), webhook signatures/duplicates/ordering, reconciliation, usage sync vs report, credits, admin access.

## Deployment (pixelforge.heptalex.ro)

Ubuntu 24.04, Nginx + PHP-FPM 8.4 (OPcache), Let's Encrypt. Three small systemd services, all as `www-data`:

- `pixelforge-queue`: `queue:work` (webhooks, the pump, image copies)
- `pixelforge-ssr`: Inertia server-side rendering (`node bootstrap/ssr/app.js`); if it's down, pages fall back to client rendering
- cron: `schedule:run` every minute (pump/reconcile safety nets, hourly usage sync, pruning)

`bin/deploy.sh` ships a release: it refuses a dirty tree, runs the type check, tests and the client + SSR build, then uploads, migrates, caches, restarts and smoke-tests (pages, SSR, unsigned webhook → 400).

Keys live only in the database, encrypted: `sudo -u www-data php artisan pixelforge:key` on each environment. `.env` holds no secrets besides `APP_KEY`.

Speed (Lighthouse, landing page): desktop 100 / 100 / 100, LCP 0.5 s; mobile performance 99. SSR, self-hosted woff2 fonts, immutable caching on hashed assets, Three.js loaded only after the page has loaded and gone idle.

## Decisions & trade-offs

- **SQLite** as requested: WAL mode, `busy_timeout`, `IMMEDIATE` transactions so writers queue instead of erroring. Writes are short and few per generation. Switching to MySQL/Postgres is an `.env` change; `lockForUpdate()` is already in place for them.
- **Polling, not WebSockets**, for live status: every 2s only for in-flight ids, paused when the tab is hidden. No extra infrastructure.
- **Email verification isn't required** to generate: credits are fake and capped by the welcome amount.
- **Three.js only on the landing page**, loaded after the page has loaded and gone idle, and skipped for reduced motion, data-saver or no WebGL, so it never affects load time.
- **"Try again"** on failed generations creates a new generation with the same settings (new estimate, new hold); its idempotency key is derived from the failed one, so a double click retries once. Not offered for content-policy rejections.
- **Reviewer access:** `php artisan app:create-reviewer <email>` makes a read-only admin login (report and audit tables; API keys and user types stay super-admin only).
- **Landing page prices** are computed by the same `PricingService` that charges users, from live `GET /v1/pricing`.
- **Known advisory:** `npm audit` reports a ReDoS in `picomatch`, used only by build tooling (never shipped to browsers); the suggested fix downgrades the Laravel Vite plugin, so it's accepted until upstream updates.

See [`docs/PLAN.md`](docs/PLAN.md) for the original build plan.
