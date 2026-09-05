<?php

declare(strict_types=1);

namespace Maryeperry\Bindle\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\BinaryFileResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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
use Maryeperry\Bindle\Storage\Models\PageComponent;
use Maryeperry\Bindle\Storage\Models\Run;
use Maryeperry\Bindle\Storage\Models\Screenshot;

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

    public function showPageCapture(int $page): View|RedirectResponse
    {
        $this->db->ensureSchema();

        $capture = Page::query()->with('run')->find($page);
        if ($capture === null) {
            return redirect()->route('bindle.panel.index')->with('bindle_error', "Unknown capture id: {$page}");
        }

        $screenshots = Screenshot::query()
            ->where('subject_type', 'page')
            ->where('subject_id', $capture->id)
            ->orderBy('id')
            ->get();

        $componentsOnPage = PageComponent::query()
            ->where('page_id', $capture->id)
            ->with(['component.props', 'component.variants'])
            ->orderBy('depth')
            ->orderBy('id')
            ->get();

        $errors = ErrorLog::query()
            ->where('run_id', $capture->run_id)
            ->where(function (Builder $q) use ($capture): void {
                $q->where(function (Builder $subject) use ($capture): void {
                    $subject->where('subject_type', 'page')
                        ->where('subject_id', $capture->id);
                })->orWhereNull('subject_type');
            })
            ->orderBy('id')
            ->get();

        return view('bindle::capture-page', [
            'page' => $capture,
            'run' => $capture->run,
            'screenshots' => $screenshots,
            'desktopScreenshot' => $screenshots->firstWhere('viewport_label', 'desktop') ?? $screenshots->first(),
            'mobileScreenshot' => $screenshots->firstWhere('viewport_label', 'mobile')
                ?? $screenshots->first(fn (Screenshot $s): bool => $s->viewport_label !== 'desktop'),
            'componentsOnPage' => $componentsOnPage,
            'errors' => $errors,
            'semanticSummary' => $this->semanticSummary($capture),
            'accessibilityNotes' => $this->accessibilityNotes($capture->run, $capture, $errors),
        ]);
    }

    public function screenshot(int $screenshot): BinaryFileResponse
    {
        $this->db->ensureSchema();

        $shot = Screenshot::query()->find($screenshot);
        if ($shot === null || ! is_file($shot->path)) {
            abort(404);
        }

        return response()->file($shot->path, [
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        ]);
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

    private function semanticSummary(Page $page): ?string
    {
        $path = rtrim((string) config('bindle.output_path', ''), '/')
            ."/pages/{$page->slug}/{$page->slug}-description.md";

        if (! is_file($path)) {
            return null;
        }

        $raw = (string) file_get_contents($path);
        $clean = trim(preg_replace('/^#.*\n/m', '', $raw) ?? '');
        if ($clean === '') {
            return null;
        }

        return substr($clean, 0, 700);
    }

    /**
     * @param  Collection<int, ErrorLog>  $errors
     * @return array<int, string>
     */
    private function accessibilityNotes(?Run $run, Page $page, Collection $errors): array
    {
        $notes = [];

        if ($run === null || ! $run->driverKind()->producesRealScreenshots()) {
            $notes[] = 'Accessibility checks are limited in this run because the placeholder driver uses empty DOM captures.';
        }

        if ($page->html_hash === null) {
            $notes[] = 'No DOM fingerprint was saved for this capture, so semantic checks could not be derived.';
        }

        $a11yErrors = $errors->filter(static function (ErrorLog $error): bool {
            return preg_match('/a11y|accessib|aria|contrast|alt text|label/i', $error->message) === 1;
        });

        foreach ($a11yErrors as $error) {
            $notes[] = $error->message;
        }

        if ($notes === []) {
            $notes[] = 'No accessibility-specific scan notes were recorded for this capture.';
        }

        return $notes;
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
