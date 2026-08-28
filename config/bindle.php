<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Bindle configuration
|--------------------------------------------------------------------------
|
| Bindle is a LOCAL / DEV-ONLY tool. It will refuse to run when APP_ENV is
| set to "production". The `enabled` flag below cannot override that — the
| service provider also checks the environment at boot. This file cannot
| weaken the production interlock.
|
*/

return [

    'enabled' => env('BINDLE_ENABLED', env('APP_ENV') !== 'production'),

    /*
    | An optional pattern matched against the host portion of `app.url`. If the
    | host matches, Bindle aborts even if `APP_ENV` is not "production" — this
    | catches misconfigured environments that point at a production host.
    |
    | Accepts either a bare host pattern ("pinkary\.com") or a fully delimited
    | regex ("/pinkary\.com$/i"); a bare value is wrapped automatically.
    */
    'production_host_pattern' => env('BINDLE_PROD_HOSTS', ''),

    /*
    | Local-only web admin panel. This is OFF by default — even installing
    | Bindle never exposes a web surface unless you opt in. On top of this
    | flag, the panel routes are ONLY registered when APP_ENV is exactly
    | "local"; production/staging never get the panel regardless of config.
    */
    'panel' => [
        'enabled' => env('BINDLE_PANEL_ENABLED', false),
        'path' => env('BINDLE_PANEL_PATH', '_bindle'),
        'middleware' => ['web'],
        'poll_seconds' => (int) env('BINDLE_PANEL_POLL', 2),
    ],

    /*
    | All Bindle output (Markdown, screenshots, SQLite DB) lives here.
    */
    'output_path' => env('BINDLE_OUTPUT_PATH', base_path('.bindle/output')),
    'database_path' => env('BINDLE_DATABASE_PATH', base_path('.bindle/bindle.sqlite')),

    /*
    | Output of panel-spawned scans. A detached background scan has nowhere
    | else to report a failure to start, so it is appended here and the panel
    | shows the tail when a run does not appear.
    */
    'log_path' => env('BINDLE_LOG_PATH', base_path('.bindle/scan.log')),

    /*
    | Viewport list used by the browser recorder for full-page screenshots.
    */
    'viewports' => [
        ['width' => 1440, 'height' => 900, 'label' => 'desktop'],
        ['width' => 390,  'height' => 844, 'label' => 'mobile'],
    ],

    /*
    | Route filtering. `include` and `exclude` accept Laravel route-name
    | patterns (wildcards supported via fnmatch syntax).
    */
    'routes' => [
        'include' => ['*'],
        'exclude' => ['horizon*', 'telescope*', '_ignition*', 'sanctum*', 'livewire*'],
        'methods' => ['GET'],
    ],

    /*
    | Where to find Blade views and JS components. These can be overridden
    | when scanning non-standard project layouts.
    */
    'paths' => [
        'views' => env('BINDLE_VIEWS_PATH', resource_path('views')),
        'js' => env('BINDLE_JS_PATH', resource_path('js')),
        'controllers' => env('BINDLE_CONTROLLERS_PATH', app_path('Http/Controllers')),
        'livewire' => env('BINDLE_LIVEWIRE_PATH', app_path('Livewire')),
    ],

    /*
    | Authentication used by PageRecorder. If `user_id` is set, the Dusk
    | session will Auth::loginAs that user before visiting routes.
    */
    'auth' => [
        'user_id' => env('BINDLE_AUTH_USER_ID'),
        'user_model' => env('BINDLE_AUTH_USER_MODEL'),
        'guard' => env('BINDLE_AUTH_GUARD', 'web'),

        /*
        | Path that auth middleware redirects guests to. When a scanned route
        | redirects here, Bindle logs a warning that it captured the login
        | page instead of the route (set `user_id` above to fix it).
        */
        'login_path' => env('BINDLE_LOGIN_PATH', '/login'),
    ],

    /*
    | Route name => URL parameter map for routes with model-bound params.
    | If a route's params aren't found here, Bindle attempts to use the
    | first DB record of the model; misses are logged as warnings.
    */
    'fixtures' => [
        // 'users.show' => ['user' => 1],
    ],

    /*
    | Path to the manifest emitted by `maryeperry-vite-plugin-bindle`.
    | If the file does not exist, Vue/React/Svelte components will not be
    | discovered (a warning is logged) but the scan otherwise continues.
    */
    'vite_manifest' => env('BINDLE_VITE_MANIFEST', public_path('build/bindle-manifest.json')),

    /*
    | Maximum concurrent browsers. Keep at 1 until your machine is happy.
    */
    'concurrency' => (int) env('BINDLE_CONCURRENCY', 1),

];
