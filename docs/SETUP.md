# Bindle setup guide

How to configure a host Laravel application so Bindle can take real
screenshots safely. Bindle drives a real browser through **Laravel Dusk**, so
most of this is standard Dusk setup plus a couple of Bindle-specific guardrails.

> Bindle is dev-only. It refuses to run when `APP_ENV=production`, and the
> `BINDLE_PROD_HOSTS` guard below stops it from ever crawling a production URL.

---

## 1. One-time install

```bash
composer require --dev maryeperry/bindle
composer require --dev laravel/dusk

php artisan dusk:install                 # creates tests/DuskTestCase.php
php artisan dusk:chrome-driver --detect  # matches chromedriver to your Chrome
php artisan bindle:install               # publishes config + tests/Browser/BindleScanTest.php
```

You also need a **Chrome browser** installed (Dusk only installs the *driver*,
not the browser). On macOS: `brew install --cask google-chrome`. If you ever
see `session not created ... cannot find Chrome binary`, this is what's missing.

---

## 2. Environment variables

Add these to your normal **`.env`**:

```dotenv
# Log in before crawling so pages behind `auth` render real content.
BINDLE_AUTH_USER_ID=1
BINDLE_AUTH_GUARD=web

# Production guard: refuse to scan if app.url's host matches.
# Accepts a bare host ("pinkary\.com") or a delimited regex ("/pinkary\.com$/i").
BINDLE_PROD_HOSTS=your-domain\.com

# Optional — only if your login route isn't /login. Used to label the
# "redirected to the login page" warning when a guarded route is hit logged-out.
# BINDLE_LOGIN_PATH=/login
```

| Variable | Purpose |
|---|---|
| `BINDLE_AUTH_USER_ID` | User to `loginAs()` before the crawl. Without it, every guarded route captures the login page. |
| `BINDLE_AUTH_GUARD` | Auth guard for the login (default `web`). |
| `BINDLE_PROD_HOSTS` | Host pattern that aborts the scan — your safety net against crawling production. |
| `BINDLE_LOGIN_PATH` | Where guests are redirected (default `/login`); only affects warning wording. |
| `BINDLE_OUTPUT_PATH` | Where screenshots/Markdown land (default `.bindle/output`). |
| `BINDLE_DATABASE_PATH` | Bindle's own SQLite store (default `.bindle/bindle.sqlite`). |

---

## 3. Point Dusk at a LOCAL server (important)

By default Dusk loads `.env.dusk.local` for its runs, **replacing your `.env`
entirely** for the duration. If that file doesn't exist it falls back to `.env`
— and if your `.env` has `APP_URL=https://your-production-site.com`, the scan
will crawl **production**.

Create **`.env.dusk.local`** as a *complete copy* of `.env` (not a one-line
override — Dusk swaps the whole file), changing only the URL:

```bash
cp .env .env.dusk.local
# then edit .env.dusk.local:
#   APP_URL=http://127.0.0.1:8000
```

> Because it's a full copy, **re-sync it whenever you change `.env`** (`cp .env
> .env.dusk.local` again, then re-set `APP_URL`) — otherwise scan runs use stale
> values.
>
> Note: `bindle:scan --driver=dusk` does **not** trip the `BINDLE_PROD_HOSTS`
> guard on your normal `.env`'s production `APP_URL`. The parent process only
> shells out to Dusk; the safety check runs inside the Dusk subprocess against
> the local `APP_URL` it actually visits.

---

## 4. Git ignore

`.env.dusk.local` contains your secrets, and `.bindle/` is generated output.
Add both to `.gitignore`:

```gitignore
.env.dusk.local
.bindle
```

---

## 5. Run it

```bash
php artisan serve                      # terminal 1 — app must be live at APP_URL
php artisan bindle:scan --driver=dusk  # terminal 2 — real Chrome, real screenshots
```

`--driver=dusk` shells out to `php artisan dusk tests/Browser/BindleScanTest.php`;
you can run that directly too. Without `--driver=dusk`, `bindle:scan` uses a
no-op browser (static component/Markdown phases only — screenshots are
placeholders).

Check results:

```bash
ls -la .bindle/output/pages/*/   # PNGs should be tens-to-hundreds of KB, not ~70 bytes
php artisan bindle:errors        # redirect / 4xx / skipped-route warnings
open .bindle/output/bindle.md
```

### Parameterized routes

Routes with model-bound params (`/users/{user}`) are skipped unless you supply
values in `config/bindle.php`:

```php
'fixtures' => [
    'users.show' => ['user' => 1],   // an ID that exists in your LOCAL database
],
```

Skips are logged to `bindle:errors`. Your local DB needs real data for these
pages to render meaningfully.

---

## Static analysis (PHPStan / Larastan)

`bindle:install` publishes a PHP file into your app at `tests/Browser/BindleScanTest.php`.
This only matters if your static analysis covers `tests/`:

- **Most setups don't.** The default Larastan config analyzes `app/` (and often `config`,
  `routes`, etc.) but not `tests/`, so the published test is never analyzed.
- **The published test is written to pass PHPStan and Larastan at `level: max`**, so if your
  analysis *does* include `tests/`, it should be clean out of the box.

If a stricter or older analyzer configuration still flags the file, exclude it:

```neon
# phpstan.neon
parameters:
    excludePaths:
        - tests/Browser/BindleScanTest.php
```

Note: this is purely about the host app's analysis of its own `tests/`. Bindle's own
`phpstan` run is internal to the package — installing Bindle never adds it to your build.

---

## Troubleshooting

| Symptom | Cause / fix |
|---|---|
| `strict_types declaration must be the very first statement` in `tests/Pest.php` | `dusk:install` inserted its `uses(...)->in('Browser')` block above `declare(strict_types=1)`. Move `declare(strict_types=1);` back to the top, directly under `<?php`. |
| `SessionNotCreatedException ... cannot find Chrome binary` | No Chrome browser installed (only the driver). Install Chrome (`brew install --cask google-chrome`), then re-run `php artisan dusk:chrome-driver --detect`. |
| The scan hit my **production** site | No `.env.dusk.local`, so Dusk used `.env` with the prod `APP_URL`. Create `.env.dusk.local` (§3) and set `BINDLE_PROD_HOSTS` (§2). |
| Screenshots are all the **login page** | Not authenticated. Set `BINDLE_AUTH_USER_ID`. The "redirected to the login page" warnings in `bindle:errors` flag exactly which routes. |
| `bindle:errors` shows many `HTTP 404` warnings | Asset/dev routes (debugbar, livewire) that aren't real pages. Exclude them via `bindle.routes.exclude`. |
| Component Markdown fails with `LazyLoadingViolationException` | Fixed in Bindle: its internal models opt out of the host app's `Model::preventLazyLoading()`. Make sure you're on a current version. |
| Screenshots are 1×1 / ~70 bytes | You ran `bindle:scan` without `--driver=dusk` (the no-op driver). Use `--driver=dusk`. |
