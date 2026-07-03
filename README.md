<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

## About

Techno Mind backend API, built on Laravel 11. Multi-tenant (single-database), token-authenticated (Sanctum access + refresh tokens) API with a per-user task manager, background job processing through Horizon, and self-generated OpenAPI docs through Scramble.

## Stack

- **PHP 8.2**, **Laravel ^11.31**
- **laravel/sanctum** — API authentication (access + refresh tokens, ability-scoped)
- **stancl/tenancy** — single-database multi-tenancy (tenant-scoped via `tenant_id`/relationship, not separate databases)
- **laravel/horizon** + **predis/predis** — Redis queue dashboard/worker, multiple named queues
- **dedoc/scramble** — generates OpenAPI docs from routes/FormRequests automatically, no annotations needed
- **laravel/pint** — code style (PSR-12)

## Setup

```bash
composer install
npm install
cp .env.example .env   # then fill in DB / Redis / Mail credentials
php artisan key:generate
php artisan migrate

composer run dev   # serve + queue:listen + pail (logs) + vite, concurrently
```

The app expects **MySQL** (`DB_CONNECTION=mysql`) and **Redis** (`QUEUE_CONNECTION=redis`, `CACHE_STORE=redis`) to be reachable using the credentials in `.env`. Mail is sent through Mailtrap SMTP in this environment (`MAIL_MAILER=smtp`).

To run the Redis queue worker (required for queued mail, see below):

```bash
php artisan horizon   # dashboard at /horizon, or:
php artisan queue:work
```

To run scheduled tasks (the overdue-task notification cron, see below), either run the scheduler loop locally or install a single cron entry that ticks it every minute:

```bash
php artisan schedule:work                       # foreground loop, useful for local dev
* * * * * php artisan schedule:run >> /dev/null  # crontab entry for real deployments
```

## Architecture

Requests flow through a **Controller → Service → Repository** layering under a versionless API namespace:

```
routes/api.php
  → App\Http\Controllers\Api\*        thin controllers, extend BaseApiController
      → App\Http\Requests\*           FormRequests, all validation lives here
      → App\Services\*                business logic (e.g. AuthService, TaskService)
          → App\Repositories\*        persistence, behind a Contracts\*Interface
      → App\Http\Resources\*          response shaping
```

- `App\Http\Controllers\Api\BaseApiController` is the shared API base controller — gives every API controller `successResponse()` / `errorResponse()` helpers plus `fromResource()` for paginated resource output, so every endpoint returns a consistent JSON envelope.
- `App\Http\Requests\BaseFormRequest` is the shared base for every FormRequest — overrides `failedValidation()` so all validation failures return a consistent `{"message": "..."}` 422, instead of Laravel's default `errors` bag shape.
- Repositories and services that need swapping/mocking are bound to interfaces in `App\Providers\AppServiceProvider::register()` (`UserRepositoryInterface`, `TenantRepositoryInterface`, `TaskRepositoryInterface`, `UserNotifierInterface`), so callers depend on the interface, not the concrete class.
- Mail is sent through queued `Mailable`s (`implements ShouldQueue`) in `App\Mail`, dispatched from services — never sent synchronously from a controller. Sending itself is isolated behind `UserNotifierInterface`/`UserNotifier`, so `AuthService` doesn't know about `Mail` or `Mailable` classes directly (keeps registration logic and notification delivery as separate concerns).

### Multi-tenancy

Single-database tenancy via `stancl/tenancy` — every `User` belongs to a `Tenant` (`tenant_id` column), and tenants share one database (no per-tenant DB/schema switching, no domain-based identification).

- **Tenant identification**: not domain/subdomain based — the `tenant` middleware (`App\Http\Middleware\InitializeTenancyFromUser`) initializes tenancy from the authenticated Sanctum user (`tenancy()->initialize($user->tenant)`) after `auth:sanctum` runs. Every protected route uses `['auth:sanctum', 'abilities:access-api', 'tenant']`.
- **`User`** is a "primary" tenant model — `use BelongsToTenant` adds a global query scope (`where tenant_id = current tenant`) and auto-fills `tenant_id` on create once tenancy is initialized.
- **`Task`** is a "secondary" model — `use BelongsToPrimaryModel` + `getRelationshipToPrimaryModel(): 'user'` scopes it via `whereHas('user')`, inheriting `User`'s tenant scope transitively. No `tenant_id` column needed on `tasks`.
- Registration (`AuthService::register()`) creates a brand-new `Tenant` ("`{name}`'s Organization") for every signup — there's no invite/join-existing-org flow.
- `config/tenancy.php`: `bootstrappers => []` (no per-tenant cache/filesystem/queue switching — only DB-row scoping is needed here), `TenancyServiceProvider`'s `TenantCreated`/`TenantDeleted` listeners are empty (no per-tenant database to create/delete).
- Full package reference: `.claude/Skills/stancl-tenancy/SKILL.md`.

### Auth

`POST /api/auth/register`, `POST /api/auth/login`, `POST /api/auth/refresh`, `POST /api/auth/logout` (`auth:sanctum` + `abilities:access-api` + `tenant`).

- Register and login both log the user in immediately and return an `access_token` and a `refresh_token` (via `UserResource` + token payload). Lifetimes come from `config('sanctum.expiration')` / `config('sanctum.refresh_expiration')` (`config/sanctum.php`), applied as each token's `expires_at`.
- Tokens are ability-scoped: access tokens get `['access-api']`, refresh tokens get `['issue-access-token']`. Every data route requires the `abilities:access-api` middleware (`Laravel\Sanctum\Http\Middleware\CheckAbilities`, aliased in `bootstrap/app.php` — Sanctum doesn't register this alias itself), so a leaked refresh token **cannot** be used to call `/api/tasks` etc., only `/api/auth/refresh`.
- `POST /api/auth/refresh` takes `{"refresh_token": "..."}` in the body (not a bearer header) and returns a new `access_token`. It deliberately does **not** go through the `auth:sanctum` guard: Sanctum's guard also enforces the global `config('sanctum.expiration')` against every token's `created_at`, which would silently cap the longer-lived refresh token at the access-token lifetime. Instead `AuthService::refresh()` resolves it manually via `PersonalAccessToken::findToken()` and checks only that token's own ability + `expires_at`. The refresh token itself is not rotated — the same one keeps working until it expires.
- Logout revokes **all** of the user's tokens (`$user->tokens()->delete()`), not just the one used for the request.
- Registration queues an onboarding email (`App\Mail\OnboardingMail`, dispatched via `UserNotifier`) on the `emails` queue — it won't send unless a queue worker is running against it (see Queues below).

### Tasks

`GET|POST /api/tasks`, `GET|PUT|PATCH|DELETE /api/tasks/{task}` (`auth:sanctum` + `abilities:access-api` + `tenant`).

- A task belongs to exactly one user (`user_id`); `status` is a backed PHP enum (`App\TaskStatusEnum`: `todo` / `in_progress` / `done`) cast onto a plain `string` column.
- `GET /api/tasks` supports `?status=`, `?search=` (title, case-insensitive substring), `?per_page=`, `?page=` — validated by `IndexTaskRequest`.
- **Caching**: a user's full task list is cached under `tasks_user_{id}` via `App\Services\Cache\CacheService`. `Task`'s model `boot()` calls `CacheService::cacheTask($task)` (`rememberForever`, no expiry) on both the `saved` and `deleted` events, so the cache is rebuilt immediately after every write — it's not TTL-driven invalidation. `TaskService::list()` reads through `CacheService::remember()` with a 1-day TTL fallback (only relevant on a genuinely cold cache, e.g. right after a `cache:clear`), then applies status/search filtering and pagination **in memory** over that cached collection — filters and pagination are not separately cached per combination, only the full per-user list is.
- All task queries are additionally scoped by `user_id` explicitly in `TaskRepository` (not just via the tenancy global scope) — ownership is enforced even if tenancy somehow isn't initialized.

### Overdue task notifications (cron)

`App\Console\Commands\NotifyOverdueTasks` (`php artisan tasks:notify-overdue`), scheduled daily in `routes/console.php`:

```php
Schedule::command(NotifyOverdueTasks::class)->daily()->withoutOverlapping()->onOneServer();
```

- Finds tasks where `due_date` is in the past, `status != done`, and `due_date_notified_at IS NULL` — the query walks the whole `tasks` table (spans every tenant; `Task::withoutParentModel()` bypasses the tenancy scope since a console command has no authenticated user to derive a tenant from) via `chunkById(200, ...)` to keep memory flat regardless of table size.
- **Speed**: the command only *enqueues* `App\Mail\TaskOverdueMail` (`ShouldQueue`, `emails` queue) per task — it never sends mail synchronously in the loop — so the command itself finishes fast no matter the volume; actual delivery happens on the queue worker.
- **Idempotency**: after a chunk is queued, those task IDs get `due_date_notified_at` set in one bulk `UPDATE` — a composite index on (`due_date`, `status`, `due_date_notified_at`) backs the lookup query. Re-running the command is a no-op for tasks already notified; it will never spam the same overdue task on every daily run.
- **Fault tolerance**: each task is dispatched inside a try/catch — one bad row logs via `Log::error()` and the loop continues rather than aborting the whole run. `TaskOverdueMail` sets `$tries = 3` / `$backoff = [10, 30, 60]`, so transient SMTP failures (seen in practice against Mailtrap under burst load) retry automatically through Laravel's queue system instead of custom retry code; a job that exhausts retries lands in `failed_jobs` rather than being silently dropped. `withoutOverlapping()` + `onOneServer()` on the schedule prevent duplicate/concurrent runs.
- `TaskOverdueMail`'s `Task` is eager-loaded with `user` before being handed to the mailable — required both to avoid `LazyLoadingViolationException` (see `preventLazyLoading` below) and because Laravel re-serializes any *loaded* relations across the queue boundary, so the worker doesn't need a fresh lazy fetch.
- Email-only for now (reuses the existing Mail/queue setup) — there's no `notifications` table/database channel in this app yet.

## Caching

`App\Services\Cache\CacheService` is a thin static wrapper around the `Cache` facade: `get()`, `forget()`, `remember()`, `rememberForever()`, plus `CACHE_TTL` (1 day) as the default TTL for anything that wants one. Domain-specific cache builders (like `cacheTask()`) live alongside it in the same class, following a `cache*()` (force rebuild) vs. read-path (`remember`-wrapped) split.

## Queues

Multiple named Redis queues, defined in `App\QueueEnum` and consumed by Horizon's `supervisor-1` (`config/horizon.php`, `'queue' => QueueEnum::list()`, `emails` listed first for priority). Adding a new named queue: give the job/mailable `->onQueue('name')` (or set `App\QueueEnum` + reference `QueueEnum::list()`), and make sure the queue name is included in the Horizon supervisor's `queue` array — otherwise nothing will ever process it.

- `emails` — `App\Mail\OnboardingMail`, `App\Mail\TaskOverdueMail`
- `default` — everything else

## API docs

Scramble auto-generates OpenAPI docs from the routes and FormRequests — no manual annotation needed.

- Interactive UI: `/docs/api`
- Raw spec export: `php artisan scramble:export` (writes `public/api.json`) — re-run after changing routes/requests, since it isn't regenerated automatically.

## Linting & formatting

```bash
composer lint            # vendor/bin/pint, PSR-12 preset (pint.json)
vendor/bin/pint --test   # check only, no changes
```

## Testing

```bash
php artisan test
vendor/bin/phpunit --filter=testName
vendor/bin/phpunit tests/Feature/ExampleTest.php
```

Test env overrides (`phpunit.xml`) run with `QUEUE_CONNECTION=sync`, `SESSION_DRIVER=array`, `CACHE_STORE=array`, `MAIL_MAILER=array`. The sqlite DB overrides are commented out, so tests currently run against the same MySQL database configured in `.env` rather than an isolated database.

## Notable app-level configuration

Set up in `App\Providers\AppServiceProvider::boot()`:

- Slow query logging — any query over 500ms is logged at `debug` level with SQL, bindings, and timing.
- `api` rate limiter — 10 requests/minute, keyed by authenticated user ID (falls back to IP for guests).
- Eloquent lazy loading is disallowed outside of production (`Model::preventLazyLoading`), so an N+1 access throws instead of silently querying. Resources that expose relations (e.g. `TaskResource`'s `user`) rely on repositories eager-loading them (`with('user')`) and use `whenLoaded()` defensively.

Set up in `bootstrap/app.php`:

- Middleware aliases: `tenant` (`InitializeTenancyFromUser`) and `abilities` (Sanctum's `CheckAbilities`, not registered by Sanctum itself in this version).
- `withExceptions()` renders every exception on JSON requests as a plain `{"message": "..."}` with the correct status code — no `exception`/`file`/`line`/`trace` leaking into API responses regardless of `APP_DEBUG` (exceptions are still fully logged to `storage/logs/laravel.log` as usual). Note: Laravel's `Handler::render()` converts `ModelNotFoundException`/`AuthorizationException` into `HttpExceptionInterface` types (`NotFoundHttpException`/`AccessDeniedHttpException`) *before* custom `render()` callbacks run, so this matches on `ValidationException`/`AuthenticationException` directly (the two types Laravel doesn't pre-convert) and on `HttpExceptionInterface` + status code for everything else — matching on the original exception classes directly doesn't work for the converted ones.
