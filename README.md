# Bindle

> **License:** Copyright © 2026 Sifrious. All rights reserved. This is
> publicly viewable proprietary software, not open-source software. See
> [LICENSE.md](LICENSE.md).

A local-only Laravel package that screenshots every page in your app, enumerates every component and prop across **Blade, Livewire, Alpine, Vue, React, and Svelte** (in both Inertia and non-Inertia projects), persists the whole graph in a self-contained SQLite database, and writes Markdown docs aimed at AI coding agents — **no AI is used to generate them**. The Markdown is composed from a hand-curated phrase dictionary.

> **Bindle refuses to run when `APP_ENV=production`.** The provider, every Artisan command, and the published config all assert this independently.

## Install

```bash
composer require --dev maryeperry/bindle
php artisan bindle:install
```

`bindle:install` publishes `config/bindle.php` and `tests/Browser/BindleScanTest.php` (the real-browser scan, see [Screenshots](#screenshots-real-browser)). If you use any JS framework (Vue/React/Svelte), also install the Vite companion:

```bash
npm install --save-dev maryeperry-vite-plugin-bindle
```

…and wire it into `vite.config.js`:

```js
import bindle from 'maryeperry-vite-plugin-bindle';
export default { plugins: [bindle()] };
```

The plugin emits `public/build/bindle-manifest.json` during your normal `npm run build`. Bindle reads it during scanning; the plugin itself does nothing at scan time.

## Usage

```bash
php artisan bindle:scan                  # static scan (no real screenshots — see below)
php artisan bindle:scan --driver=dusk    # full scan WITH real screenshots (drives Chrome)
php artisan bindle:scan --only=markdown  # regenerate just the .md files
php artisan bindle:scan --fresh          # wipe prior data first
php artisan bindle:errors                # tabular dump of the errors table
php artisan bindle:reset                 # wipe SQLite + output directory
```

> **Heads-up:** plain `bindle:scan` uses a **no-op browser**. The route, component,
> and Markdown phases all run, but the page screenshots are 1×1 placeholders and the
> rendered DOM is empty (so DOM-derived data like Alpine bindings won't be found).
> For real screenshots you must use `--driver=dusk` — see [Screenshots](#screenshots-real-browser).
>
> You are never left guessing which you got. Every run stores its driver, a
> placeholder run logs a warning saying what it could not capture (`php artisan
> bindle:errors`), and `--driver=dusk` refuses to start unless every precondition is
> met — printing each missing one with the command that fixes it.

You can also scan a single route instead of the whole app:

```bash
php artisan bindle:scan --route=dashboard   # by route name
php artisan bindle:scan --route=/users       # or by URI
```

## Admin panel (local only)

## Code-inspection seam

Bindle owns code-understanding evidence; a host such as Stacks supplies only a
workspace identifier, checkout root, optional relative path, and revision. Resolve
`Maryeperry\Bindle\Inspection\Contracts\InspectionProvider` from the container and
pass an `InspectionRequest`. The default `PhpInspectionProvider` returns typed,
non-persistent `InspectionSnapshot` evidence for PHP symbols and relationships plus
Blade/Inertia resource discovery. Applications may bind another provider without
changing the read model.

The namespaced anonymous Blade components under
`<x-bindle::inspection.*>` render symbol lists/rows, code outlines, inspection
summaries, dependency diagrams with complete textual equivalents, route lists,
component inventories, colocation findings, and ordinary-link inspection tabs.
They are server-rendered and deliberately do not create a second Stacks symbol or
inspection store.

The precise Landing-to-Bindle behavior map is documented in
[`docs/landing-inspection-parity.md`](docs/landing-inspection-parity.md).

Bindle ships an optional web panel that lists every route and component on one
page and gives you buttons to trigger a **full scan** or a **single-page scan** —
each runs `bindle:scan` in the background while a status page polls for completion.

Each scan form carries a **driver dropdown**. Dusk only appears in it once every
requirement for real screenshots is met; until then the panel says so plainly,
lists what is missing, and offers a **Run this** button beside each fix that is an
Artisan command. Asking for Dusk while it cannot run is refused with the list of
what is missing — a request for real screenshots is never quietly downgraded to
placeholders.

Every run records the driver that produced it, shown as a badge on the panel and
the status page, so a placeholder run cannot be mistaken for a real one later.

It is **off by default** and only ever registers its routes when `APP_ENV=local`.
Turn it on in `.env`:

```dotenv
BINDLE_PANEL_ENABLED=true
```

Then visit **`/_bindle`**. Configuration lives in the `panel` block of
`config/bindle.php` (publishable via `php artisan vendor:publish --tag=bindle-config`):

| Key (env) | Default | Purpose |
|---|---|---|
| `panel.enabled` (`BINDLE_PANEL_ENABLED`) | `false` | Master switch. Even when `true`, the panel only mounts in `local`. |
| `panel.path` (`BINDLE_PANEL_PATH`) | `_bindle` | URL prefix for the panel. |
| `panel.middleware` | `['web']` | Middleware stack; `EnsureLocalAndEnabled` (a 404 gate) is always appended. |
| `panel.poll_seconds` (`BINDLE_PANEL_POLL`) | `2` | Status-page refresh interval while a scan runs. |
| `log_path` (`BINDLE_LOG_PATH`) | `.bindle/scan.log` | Where panel-spawned scans write stdout and stderr. The status page shows the tail. |

The panel is gated three ways: the provider only registers the routes in `local`
with the flag on, a request-time middleware re-checks and returns **404** otherwise,
and `ScanRunner` calls `Environment::assertSafe()` before spawning any scan.

### Screenshots (real browser)

> **Full setup walkthrough — including environment variables, pointing Dusk at a
> local server, and troubleshooting — lives in [docs/SETUP.md](docs/SETUP.md).**
> The summary below is the short version.

Real screenshots are taken by driving Chrome through **Laravel Dusk**. Dusk owns its
own bootstrapping, so Bindle ships the work as a published Dusk test
(`tests/Browser/BindleScanTest.php`) and runs it for you.

One-time setup:

```bash
composer require --dev laravel/dusk
php artisan dusk:install              # creates tests/DuskTestCase.php (the base BindleScanTest extends)
php artisan dusk:chrome-driver --detect
php artisan bindle:install            # publishes tests/Browser/BindleScanTest.php
```

Then, with your app actually serving (Dusk visits real URLs):

```bash
php artisan serve                     # in one terminal — app must be reachable at APP_URL
php artisan bindle:scan --driver=dusk # in another
```

`--driver=dusk` shells out to `php artisan dusk tests/Browser/BindleScanTest.php`. You can
run that command directly if you prefer.

**Authenticated pages.** Routes behind `auth` only render their real content if Bindle
logs in first. Set the user in `config/bindle.php` (or env):

```dotenv
BINDLE_AUTH_USER_ID=1
BINDLE_AUTH_GUARD=web
BINDLE_LOGIN_PATH=/login   # where guests get redirected (used for the warning below)
```

`BindleScanTest` calls `$browser->loginAs(...)` with these before crawling.

If a route still redirects to the login page (or anywhere else), or renders a 4xx/5xx
page in place, Bindle **logs a warning** rather than silently saving the wrong screenshot.
Check it with `php artisan bindle:errors` — you'll see entries like
*"Route [dashboard] redirected to the login page — set BINDLE_AUTH_USER_ID"*. The
screenshot is still written, but the warning tells you it isn't the page you wanted.

**Parameterized routes.** Routes with model-bound parameters (`/users/{user}`) are skipped
unless you supply values in `bindle.fixtures`, e.g. `'users.show' => ['user' => 1]`. Skips
are logged — see `php artisan bindle:errors`.

Output lands under `.bindle/output/`:

```
.bindle/output/
├── bindle.md                                  index
├── pages/
│   └── <page-slug>/
│       ├── <page-slug>-detail-desktop.png     full-page screenshot
│       ├── <page-slug>-detail-mobile.png
│       ├── <page-slug>-audit.md               components + props passed
│       └── <page-slug>-description.md         layout description (phrase-built)
└── components/
    └── <component-slug>/
        ├── <component-slug>-detail.md         variants + prop signatures
        └── <component-slug>-page-audit.md     routes + parents that reference it
```

The SQLite store lives at `.bindle/bindle.sqlite` and is independent of your app's database connection.

## How prop discovery works

| Framework | How props are found |
|---|---|
| Blade anonymous components | `@props([...])` directive at top of `.blade.php` |
| Blade class components | Reflection on the Component class' constructor + public props |
| Livewire | Reflection on `Livewire\Component` subclasses |
| Alpine | Inline `x-data`, `x-bind:*`, `x-on:*`, `x-model` scanned from rendered DOM |
| Inertia pages | AST walk of controllers for `Inertia::render('Page', [...])` |
| Vue / React / Svelte | The Vite plugin writes them to `bindle-manifest.json` |

## Production safety

Three independent guards have to fail for Bindle to run:

1. The service provider's `register()` is a no-op when `app.environment() === 'production'`.
2. Every Artisan command calls `Environment::assertSafe()` first, which throws `ProductionForbiddenException` on `production`, on `bindle.enabled=false`, or when `app.url`'s host matches `bindle.production_host_pattern`.
3. The published config seeds `'enabled' => env('APP_ENV') !== 'production'`.

The [admin panel](#admin-panel-local-only) is stricter still: its routes only mount when `APP_ENV` is exactly `local` **and** `bindle.panel.enabled` is true, with a request-time middleware that 404s anything else.

## Development

```bash
composer test          # lint, typos, unit, types, refactor dry-run
composer test:unit     # just pest
composer lint          # auto-fix with Pint
```
