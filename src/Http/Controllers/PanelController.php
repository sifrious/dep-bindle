<?php

declare(strict_types=1);

namespace Maryeperry\Bindle\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Maryeperry\Bindle\Browser\DriverAvailability;
use Maryeperry\Bindle\Browser\DriverKind;
use Maryeperry\Bindle\Http\InstallRunner;
use Maryeperry\Bindle\Http\ScanRunner;
use Maryeperry\Bindle\Routes\ResolvedRoute;
use Maryeperry\Bindle\Routes\RouteEnumerator;
use Maryeperry\Bindle\Storage\Database\DatabaseManager;
use Maryeperry\Bindle\Storage\Models\Component;
use Maryeperry\Bindle\Storage\Models\ErrorLog;
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
        private DriverAvailability $availability,
        private InstallRunner $installer,
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
            'driverKinds' => $this->availability->availableKinds(),
            'duskRequirements' => $this->availability->requirements(DriverKind::Dusk),
            'duskAvailable' => $this->availability->isAvailable(DriverKind::Dusk),
        ]);
    }

    public function scanAll(Request $request): RedirectResponse
    {
        $driver = $this->requestedDriver($request);

        if ($driver === null) {
            return $this->refuseUnavailableDriver();
        }

        $this->runner->spawn(null, true, $driver);

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

        $driver = $this->requestedDriver($request);

        if ($driver === null) {
            return $this->refuseUnavailableDriver();
        }

        $this->runner->spawn($target, false, $driver);

        return redirect()->route('bindle.panel.latest-status');
    }

    public function install(Request $request): RedirectResponse
    {
        $result = $this->installer->run((string) $request->input('action'));

        return redirect()->route('bindle.panel.index')
            ->with($result['ok'] ? 'bindle_notice' : 'bindle_error', $result['output'] === ''
                ? 'Install command finished.'
                : $result['output']);
    }

    public function status(int $run): View
    {
        $this->db->ensureSchema();

        return $this->statusView(Run::query()->find($run));
    }

    public function latestStatus(): RedirectResponse|View
    {
        $this->db->ensureSchema();

        $run = Run::query()->latest('id')->first();

        if ($run !== null) {
            return redirect()->route('bindle.panel.status', ['run' => $run->id]);
        }

        return $this->statusView(null);
    }

    private function statusView(?Run $run): View
    {
        $problems = $run === null
            ? collect()
            : ErrorLog::query()
                ->where('run_id', $run->id)
                ->whereIn('severity', ['error', 'fatal'])
                ->orderBy('id')
                ->get();

        return view('bindle::status', [
            'run' => $run,
            'pollSeconds' => (int) config('bindle.panel.poll_seconds', 2),
            'problems' => $problems,
            'logTail' => $this->runner->tailLog(),
            'logPath' => $this->runner->logPath(),
        ]);
    }

    /**
     * The driver the form asked for, or null when it is not usable here. A
     * request for real screenshots is never quietly downgraded to placeholders.
     */
    private function requestedDriver(Request $request): ?DriverKind
    {
        $driver = DriverKind::fromOption($request->input('driver'));

        return $this->availability->isAvailable($driver) ? $driver : null;
    }

    private function refuseUnavailableDriver(): RedirectResponse
    {
        $missing = array_map(
            static fn ($requirement): string => $requirement->label,
            $this->availability->unmet(DriverKind::Dusk),
        );

        return redirect()->route('bindle.panel.index')->with(
            'bindle_error',
            'Real screenshots are not available yet — still missing: '.implode('; ', $missing).'.',
        );
    }
}
