# Pixelforge × PromptEdit — build plan

Target: `https://pixelforge.heptalex.ro` · public GitHub repo · Laravel 13 + Inertia 3 + Vue 3 + Tailwind 4 + Three.js · MoonShine 4 admin · SQLite.

## Ground rules from the assessment (non‑negotiable)

- Laravel + **Vue + Inertia** (required by PromptEdit).
- Users pay **Pixelforge cost + 25 %**. Estimate up front, record actual cost on completion. **Failed = free.**
- Webhooks: verify HMAC signature, dedupe by event id, survive duplicates / out‑of‑order / early arrival. **Never charge twice.**
- History with live status, estimated vs actual cost, image.
- Stretch: admin report (charged / provider cost / profit per day & per user, matching `GET /v1/usage`) and credit balances with "Buy credits".
- "Correctness ahead of polish."

## Reality check on "100k concurrent"

Pixelforge allows **3 generations at once, 60 requests/min, 500/day per account**. So:
- **Our side** accepts and stores any burst instantly (idempotent insert + queued job). That part scales.
- **Provider side** drains through a limiter at 3 concurrent / 60 rpm. Requests wait in `queued_local` state; the UI shows that honestly.
- SQLite has one writer at a time. WAL mode + short transactions + `busy_timeout` handle the assessment load; the schema is portable to MySQL/Postgres with a single env change.

## Money model

- All amounts are **integer nanodollars** (`BIGINT`, 1 USD = 1 000 000 000). No floats anywhere.
- Rate `"5.00"` per 1M tokens = `5000` nano‑USD per token (parsed with bcmath, never float).
- `provider_cost = Σ tokens_k × rate_k` (exact). `charge = ceil(provider_cost × 125 / 100)` (rounds up by at most 1 nano‑USD, documented).
- Estimate shown before submit: min / expected / max (text ±15 %, reasoning range by quality).
- Balance: a **hold** of the max charge on submit, then **capture** the actual charge and release the rest, or **release** fully on failure. Ledger rows carry `UNIQUE(generation_id, type)`, so a duplicated webhook cannot write a second charge.

## Data model (indexes noted)

| Table | Purpose | Key indexes / constraints |
|---|---|---|
| `user_types` | user, admin, super_admin (+ any new) | `UNIQUE(slug)`; `level` int; `is_admin` bool |
| `users` | Laravel auth users | `UNIQUE(email)`; FK `user_type_id` + index; `balance_nano` cached |
| `api_keys` | Pixelforge keys per environment | `UNIQUE(environment)` where active; key + webhook secret **encrypted** cast |
| `generations` | one row per user request | PK ULID; `UNIQUE(user_id, idempotency_key)`; `UNIQUE(provider_id)` nullable; `(user_id, created_at)`; `(status, updated_at)` for the reconciler |
| `credit_ledger` | purchases, holds, captures, releases | `UNIQUE(generation_id, type)`; `(user_id, created_at)` |
| `api_calls` | every outbound call to Pixelforge | `(generation_id)`, `(created_at)`, `(status_code)`; latency, attempt, retry_after, error code |
| `api_logs` | request/response bodies (pruned after 14 days) | FK `api_call_id`; `(created_at)` for pruning |
| `webhook_events` | inbound events | `UNIQUE(event_id)`; `(provider_generation_id)`; `processed_at` |
| `provider_usage_daily` | snapshot of `GET /v1/usage` | `UNIQUE(api_key_id, date)` |
| `jobs`, `failed_jobs`, `cache`, `sessions` | Laravel built‑ins | defaults |

## Request pipeline (model → middleware → controller → service)

1. **Middleware**: `auth`, `verified`, per‑user rate limit (`throttle:generations`), `EnsureUserType` for admin routes.
2. **FormRequest** validates exactly what Pixelforge accepts (size, quality, ≤4 URLs, ≤4000 chars), so we never send a 422.
3. **Controller** (thin) → `GenerationService::create()`:
   - DB transaction: lock user row, check balance ≥ max charge, insert generation (`queued_local`) with a client‑sent `Idempotency-Key` (double‑click safe), write the hold.
   - Dispatch `SubmitGeneration` (unique job per generation).
4. **`PixelforgeClient`** (one class, all HTTP): bearer auth, timeouts, logs to `api_calls`/`api_logs`, maps error codes to typed exceptions.
5. **`SubmitGeneration` job**: provider limiter (3 concurrent / 60 rpm, cache‑lock semaphore) → `POST /v1/generations` with `Idempotency-Key = generation ULID` and `metadata.generation_id`.
   - 429/503 → release with `Retry-After` + exponential backoff + jitter, max 6 tries, then `failed` + full release.
   - 400 content policy / 422 → `failed`, release, show the reason.
6. **Webhook** `POST /webhooks/pixelforge` (no CSRF, no session): verify signature on the raw body, reject timestamps older than 300 s, `insertOrIgnore` into `webhook_events`, return 204 fast, dispatch `ProcessWebhookEvent`.
7. **State machine**: `queued_local → queued → processing → succeeded|failed`. Updates are conditional (`WHERE status rank < new rank`), so late or duplicate events are no‑ops. Match by `metadata.generation_id`, so a webhook that beats the POST response still lands.
8. **Finalise** (one transaction): compute cost from `usage × rates` snapshot, capture the charge, release the remainder. Idempotent through the ledger constraint.
9. **Reconciler** (scheduler, every minute): poll `GET /v1/generations/{id}` for anything stuck in `queued/processing` > 3 min (webhooks are not guaranteed). Daily: pull `GET /v1/usage` into `provider_usage_daily`.
10. **Images**: download on success to `storage/app/public/generations`, so history keeps working after the 24 h URL expiry.

## Frontend (budget: < 1.5 s load)

- Inertia SPA, route‑level code splitting, Tailwind 4 (only used CSS ships).
- Three.js is **lazy‑loaded after first paint** (dynamic `import()`), small hero scene, paused off‑screen, off for `prefers-reduced-motion` and low‑power devices. Never blocks LCP.
- Live status: short polling on History (2 s while anything is in flight, stops when idle). No WebSocket server needed.
- Look: matches Pixelforge/PromptEdit — near‑black, white type, mono details, one warm accent (no blue).
- Pages: Generate (prompt, size, quality, reference URLs, live cost estimate), History (grid, status chips, estimated vs actual), Credits (balance, buy credits), Auth (starter kit).

## Admin (MoonShine 4 at `/admin`)

- Uses the **same `users` table and `web` guard** (no separate moonshine_users). Access via `EnsureUserType:admin`.
- Resources: Users, User types, API keys (secrets masked), Generations, Credit ledger, API calls / logs, Webhook events.
- Report page: per day & per user → charged, provider cost, profit; per day vs `provider_usage_daily` with a match/mismatch flag.
- super_admin only: user types, API keys.

## Security & repo hygiene (public repo)

- `.env`, `database/*.sqlite`, `storage/*.key`, logs ignored; `.env.example` has placeholders only.
- Secrets in DB use Laravel's `encrypted` cast; never logged (`api_logs` redacts `Authorization`).
- `gitleaks` scan before the first push.
- Webhook route: signature + timestamp check, body size limit, rate limit.
- Seeders create demo users with random passwords printed once, never committed.

## Testing

- Pest feature tests: pricing maths (docs worked example = `0.373875000`), duplicate webhook, out‑of‑order events, webhook before POST returns, 429/503 retry, failed = free, bad signature = 400.
- Live tests with prompt keywords: `[fail]`, `[slow]`, `[duplicate]`, `[overloaded]`, `[blocked]`.
- Lighthouse (local Chrome) on Generate and History: LCP < 1.5 s.

## Deploy (pixelforge.heptalex.ro)

- Server `ubuntu@heptalex.ro`: Nginx + PHP‑FPM + OPcache, Brotli/gzip, immutable cache on `/build`.
- Supervisor: queue workers (`submit`, `webhooks` queues) + cron for the scheduler.
- `php artisan optimize` (config/route/view cache); Let's Encrypt.
- Two Pixelforge keys: **local** (webhook via tunnel) and **deployed** (webhook `https://pixelforge.heptalex.ro/webhooks/pixelforge`).

## Step‑by‑step

1. ✅ Scaffold: Laravel 13 Vue starter kit (Inertia 3, Tailwind 4, Fortify auth), MoonShine 4, Three.js, SQLite, git.
2. Schema: migrations + models + factories for all tables above; drop MoonShine's own user tables; SQLite WAL pragmas.
3. User types + `EnsureUserType` middleware; MoonShine on the `web` guard.
4. `Money` value object + `PricingService` (estimate & actual) with tests.
5. `PixelforgeClient` + `api_calls`/`api_logs` logging.
6. Generation flow: FormRequest, controller, service, `SubmitGeneration` job with limiter and retries.
7. Webhook controller + `ProcessWebhookEvent` + state machine + finalise + tests.
8. Reconciler + usage sync (scheduler).
9. Balances: ledger, hold/capture/release, Buy credits.
10. Frontend: Generate, History, Credits pages; lazy Three.js hero.
11. MoonShine resources + report page.
12. Security pass + gitleaks + README (setup, decisions, trade‑offs).
13. Deploy to pixelforge.heptalex.ro, set webhook, run keyword tests, leave sample generations.
14. Lighthouse + final check against "What we look at" → submit.

## Local dev notes

- This PC's `php` is the FrankenPHP wrapper; it rejects `php -d …`, which Composer's `@php` scripts use. Run Composer with `--no-scripts`, then `php artisan package:discover`.
