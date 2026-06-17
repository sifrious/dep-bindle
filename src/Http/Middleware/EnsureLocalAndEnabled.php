<?php

declare(strict_types=1);

namespace Maryeperry\Bindle\Http\Middleware;

use Closure;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Maryeperry\Bindle\Support\Environment;
use Symfony\Component\HttpFoundation\Response;

/**
 * Defense-in-depth gate for the admin panel. The service provider already
 * refuses to register the panel routes outside `local` + the config flag, but
 * this middleware re-checks at request time and 404s (not 403s) so the panel
 * is invisible — never even hinting it exists — if anything slips through.
 */
final readonly class EnsureLocalAndEnabled
{
    public function __construct(
        private Application $app,
        private Environment $env,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->app->environment('local')) {
            abort(404);
        }

        if (! (bool) config('bindle.panel.enabled')) {
            abort(404);
        }

        if (! $this->env->isSafe()) {
            abort(404);
        }

        return $next($request);
    }
}
