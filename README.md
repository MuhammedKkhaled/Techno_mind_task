<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

## About

Techno Mind backend API, built on Laravel 11. Currently implements token-based authentication (register / login / logout) via Sanctum, with background job processing through Horizon and self-generated OpenAPI docs through Scramble.

## Stack

- **PHP 8.2**, **Laravel ^11.31**
- **laravel/sanctum** — API authentication (access + refresh tokens)
- **laravel/horizon** + **predis/predis** — Redis queue dashboard/worker
- **dedoc/scramble** — generates OpenAPI docs from routes/FormRequests automatically, no annotations needed
- **stancl/tenancy** — installed for future multi-tenancy; not configured or wired up yet
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

## Architecture

Requests flow through a **Controller → Service → Repository** layering under a versionless API namespace:

```
routes/api.php
  → App\Http\Controllers\Api\*        thin controllers, extend BaseApiController
      → App\Http\Requests\*           FormRequests, all validation lives here
      → App\Services\*                business logic (e.g. AuthService)
          → App\Repositories\*        persistence, behind a Contracts\*Interface
      → App\Http\Resources\*          response shaping
```

- `App\Http\Controllers\Api\BaseApiController` is the shared API base controller — gives every API controller `successResponse()` / `errorResponse()` helpers plus `fromResource()` for paginated resource output, so every endpoint returns a consistent JSON envelope.
- Repositories are bound to their interfaces in `App\Providers\AppServiceProvider::register()` (e.g. `UserRepositoryInterface` → `UserRepository`), so services depend on the interface, not Eloquent directly.
- Mail is sent through queued `Mailable`s (`implements ShouldQueue`) in `App\Mail`, dispatched from services — never sent synchronously from a controller.

### Auth

`POST /api/auth/register`, `POST /api/auth/login`, `POST /api/auth/logout` (auth:sanctum).

- Register and login both log the user in immediately and return an `access_token` and a `refresh_token` (via `UserResource` + token payload), sourced from `config('sanctum.expiration')` / `config('sanctum.refresh_expiration')` (see `config/sanctum.php`).
- Logout revokes **all** of the user's tokens (`$user->tokens()->delete()`), not just the one used for the request.
- Registration queues an onboarding email (`App\Mail\OnboardingMail`) — it won't send unless a queue worker (`queue:work` / `horizon`) is running against the `redis` connection.

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
- Eloquent lazy loading is disallowed outside of production (`Model::preventLazyLoading`), so an N+1 access throws instead of silently querying.
