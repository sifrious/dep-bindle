<?php

declare(strict_types=1);

namespace Maryeperry\Bindle\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Maryeperry\Bindle\Http\ScanRunner;
use Maryeperry\Bindle\Routes\ResolvedRoute;
use Maryeperry\Bindle\Routes\RouteEnumerator;
use Maryeperry\Bindle\Storage\Database\DatabaseManager;
use Maryeperry\Bindle\Storage\Models\Component;
use Maryeperry\Bindle\Storage\Models\Page;
use Maryeperry\Bindle\Storage\Models\Run;

/**
 * Local-only web admin panel. Lists every route and component and lets the
 * developer trigger a full or single-page scan, which runs in the background
 * while the status page polls the `runs` table for completion.
 *
 * Every read path calls DatabaseManager::ensureSchema() first — the dedicated
 * `bindle` SQLite connection only exists once that has registered it.
 */
final readonly class PanelController
{
    public function __construct(
        private RouteEnumerator $routes,
        private DatabaseManager $db,
        private ScanRunner $runner,
    ) {}

    public function index(): View
    {
        $this->db->ensureSchema();

        $routes = $this->routes->enumerate();

        $latest = Run::query()->where('status', 'completed')->latest('id')->first();

        $components = $latest !== null
            ? Component::query()->where('run_id', $latest->id)->orderBy('kind')->orderBy('name')->get()
            : collect();

        $scannedPages = $latest !== null
            ? Page::query()->where('run_id', $latest->id)->get()
                ->keyBy(fn (Page $p): string => $p->route_name ?? $p->uri)
            : collect();

        $running = Run::query()->where('status', 'running')->latest('id')->first();

        return view('bindle::index', [
            'routes' => $routes,
            'components' => $components,
            'scannedPages' => $scannedPages,
            'latest' => $latest,
            'running' => $running,
        ]);
    }

    public function scanAll(): RedirectResponse
    {
        // (route: null, fresh: true) — scan every route, wiping prior data.
        $this->runner->spawn(null, true);

        return redirect()->route('bindle.panel.latest-status');
    }

    public function scanPage(Request $request): RedirectResponse
    {
        $target = trim((string) $request->input('route'));

        $match = collect($this->routes->enumerate())->first(
            fn (ResolvedRoute $r): bool => $r->name === $target
                || $r->uri === $target
                || $r->identifier() === $target,
        );

        if ($match === null) {
            return redirect()->route('bindle.panel.index')
                ->with('bindle_error', "Unknown route: {$target}");
        }

        // (route: $target, fresh: false) — scan one route, keeping prior data.
        $this->runner->spawn($target, false);

        return redirect()->route('bindle.panel.latest-status');
    }

    public function status(int $run): View
    {
        $this->db->ensureSchema();

        return view('bindle::status', [
            'run' => Run::query()->find($run),
            'pollSeconds' => (int) config('bindle.panel.poll_seconds', 2),
        ]);
    }

    public function latestStatus(): RedirectResponse|View
    {
        $this->db->ensureSchema();

        $run = Run::query()->latest('id')->first();

        if ($run !== null) {
            return redirect()->route('bindle.panel.status', ['run' => $run->id]);
        }

        return view('bindle::status', [
            'run' => null,
            'pollSeconds' => (int) config('bindle.panel.poll_seconds', 2),
        ]);
    }
}
