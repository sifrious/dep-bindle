<?php

declare(strict_types=1);

namespace Maryeperry\Bindle\Pipeline;

use Illuminate\Contracts\Config\Repository;
use Maryeperry\Bindle\Browser\BrowserDriver;
use Maryeperry\Bindle\Browser\CapturedPage;
use Maryeperry\Bindle\Browser\NullBrowserDriver;
use Maryeperry\Bindle\Browser\PageRecorder;
use Maryeperry\Bindle\Generators\MarkdownGenerator;
use Maryeperry\Bindle\Routes\ResolvedRoute;
use Maryeperry\Bindle\Routes\RouteEnumerator;
use Maryeperry\Bindle\Scanners\AlpineScanner;
use Maryeperry\Bindle\Scanners\BladeScanner;
use Maryeperry\Bindle\Scanners\DiscoveredCallSite;
use Maryeperry\Bindle\Scanners\DiscoveredComponent;
use Maryeperry\Bindle\Scanners\InertiaScanner;
use Maryeperry\Bindle\Scanners\LivewireScanner;
use Maryeperry\Bindle\Scanners\ManifestScanner;
use Maryeperry\Bindle\Storage\Database\DatabaseManager;
use Maryeperry\Bindle\Storage\ErrorLogger;
use Maryeperry\Bindle\Storage\Models\Component;
use Maryeperry\Bindle\Storage\Models\Page;
use Maryeperry\Bindle\Storage\Models\PageComponent;
use Maryeperry\Bindle\Storage\Models\Prop;
use Maryeperry\Bindle\Storage\Models\Run;
use Maryeperry\Bindle\Storage\Models\Screenshot;
use Maryeperry\Bindle\Support\Environment;
use Maryeperry\Bindle\Support\Slug;

/**
 * Orchestrates a complete Bindle scan. Steps:
 *   1. assertSafe()
 *   2. ensureSchema() + create runs row
 *   3. enumerate routes
 *   4. for each route: record screenshot + HTML, persist Page + Screenshots
 *   5. discover components from every scanner, persist Component + Prop rows
 *   6. for each page: resolve call-sites from scanners, persist PageComponent rows
 *   7. generate Markdown
 *   8. finalize run
 */
final class ScanPipeline
{
    public function __construct(
        private readonly Environment $env,
        private readonly DatabaseManager $db,
        private readonly Repository $config,
        private readonly RouteEnumerator $routes,
        private readonly BladeScanner $blade,
        private readonly LivewireScanner $livewire,
        private readonly AlpineScanner $alpine,
        private readonly InertiaScanner $inertia,
        private readonly ManifestScanner $manifest,
        private readonly MarkdownGenerator $markdown,
        private readonly ErrorLogger $errors,
    ) {}

    /**
     * @param  array<string>  $only  one or more of: routes, screenshots, components, markdown
     */
    public function run(?BrowserDriver $driver = null, array $only = [], bool $fresh = false): Run
    {
        $this->env->assertSafe();
        $this->db->ensureSchema();

        if ($fresh) {
            $this->db->truncateAll();
        }

        $run = Run::create([
            'environment' => (string) $this->config->get('app.env', 'local'),
            'status' => 'running',
            'bindle_version' => '0.1.0-dev',
            'git_sha' => $this->gitSha(),
        ]);
        $this->errors->setRunId((int) $run->id);

        $driver ??= new NullBrowserDriver;
        $doAll = $only === [];

        $resolvedRoutes = $this->routes->enumerate();
        $pageRecorder = new PageRecorder($this->config, $driver, $this->errors);

        /** @var array<int, string> $renderedHtmlByPageId */
        $renderedHtmlByPageId = [];

        // ── Step 4: routes + screenshots ────────────────────────────────────
        $pages = [];
        foreach ($resolvedRoutes as $resolved) {
            $page = $this->persistPage($run, $resolved);
            $pages[] = [$page, $resolved];

            if (! $doAll && ! in_array('screenshots', $only, true) && ! in_array('routes', $only, true)) {
                continue;
            }

            $captured = $pageRecorder->record($resolved);
            if ($captured !== null) {
                $renderedHtmlByPageId[(int) $page->id] = $captured->html;
                $page->html_hash = $captured->htmlHash;
                $page->save();
                $this->persistScreenshots($page, $captured);
            }
        }

        // ── Step 5: discover components ─────────────────────────────────────
        if ($doAll || in_array('components', $only, true)) {
            $components = $this->discoverAllComponents($run);
        } else {
            $components = [];
        }

        // ── Step 6: resolve call-sites + persist page_components ────────────
        if ($doAll || in_array('components', $only, true)) {
            foreach ($pages as [$page, $resolved]) {
                /** @var Page $page */
                /** @var ResolvedRoute $resolved */
                $html = $renderedHtmlByPageId[(int) $page->id] ?? null;
                $this->persistCallSites($run, $page, $resolved, $html, $components);
            }
        }

        // ── Step 7: markdown ────────────────────────────────────────────────
        if ($doAll || in_array('markdown', $only, true)) {
            $run->refresh()->load(['pages.pageComponents.component', 'components.props']);
            $this->markdown->generate($run, $renderedHtmlByPageId);
        }

        // ── Step 8: finalize ────────────────────────────────────────────────
        $run->update([
            'finished_at' => now(),
            'status' => 'completed',
        ]);

        return $run;
    }

    private function persistPage(Run $run, ResolvedRoute $resolved): Page
    {
        $slug = Slug::forRoute($resolved->name, $resolved->uri, $resolved->method);

        return Page::create([
            'run_id' => $run->id,
            'route_name' => $resolved->name,
            'uri' => $resolved->uri,
            'http_method' => $resolved->method,
            'slug' => $slug,
            'controller' => $resolved->controller,
            'view' => $resolved->view,
            'framework' => $resolved->framework,
        ]);
    }

    private function persistScreenshots(Page $page, CapturedPage $captured): void
    {
        foreach ($captured->screenshotPaths as $label => $path) {
            $viewport = $this->viewportByLabel($label);
            Screenshot::create([
                'subject_type' => 'page',
                'subject_id' => $page->id,
                'viewport_w' => $viewport['width'] ?? 1440,
                'viewport_h' => $viewport['height'] ?? 900,
                'viewport_label' => $label,
                'path' => $path,
            ]);
        }
    }

    /**
     * @return array<string, Component> keyed by "kind:name"
     */
    private function discoverAllComponents(Run $run): array
    {
        $components = [];

        $scanners = [$this->blade, $this->livewire, $this->inertia, $this->manifest];

        foreach ($scanners as $scanner) {
            try {
                /** @var DiscoveredComponent $discovered */
                foreach ($scanner->discover() as $discovered) {
                    $key = $discovered->kind.':'.$discovered->name;
                    if (isset($components[$key])) {
                        continue;
                    }

                    $component = Component::create([
                        'run_id' => $run->id,
                        'name' => $discovered->name,
                        'slug' => Slug::forComponent($discovered->kind, $discovered->name),
                        'kind' => $discovered->kind,
                        'source_path' => $discovered->sourcePath,
                        'signature_hash' => $discovered->signatureHash,
                    ]);

                    foreach ($discovered->props as $prop) {
                        Prop::create([
                            'component_id' => $component->id,
                            'name' => $prop->name,
                            'type' => $prop->type,
                            'default_value' => $prop->defaultValue,
                            'required' => $prop->required,
                            'source' => $prop->source,
                        ]);
                    }

                    $components[$key] = $component;
                }
            } catch (\Throwable $e) {
                $this->errors->error(ErrorLogger::PHASE_SCAN, 'Scanner '.$scanner::class." failed: {$e->getMessage()}", [
                    'scanner' => $scanner::class,
                ]);
            }
        }

        return $components;
    }

    private function persistCallSites(Run $run, Page $page, ResolvedRoute $resolved, ?string $html, array &$components): void
    {
        $scanners = [$this->blade, $this->alpine, $this->manifest];

        foreach ($scanners as $scanner) {
            try {
                /** @var DiscoveredCallSite $callSite */
                foreach ($scanner->callSitesIn($resolved->view, $html) as $callSite) {
                    $key = $callSite->componentKind.':'.$callSite->componentName;
                    $component = $components[$key] ?? null;

                    if ($component === null) {
                        // Synthetic — create on demand (used for Alpine etc. where
                        // there's no defined-elsewhere component).
                        $component = Component::create([
                            'run_id' => $run->id,
                            'name' => $callSite->componentName,
                            'slug' => Slug::forComponent($callSite->componentKind, $callSite->componentName),
                            'kind' => $callSite->componentKind,
                            'source_path' => null,
                        ]);
                        $components[$key] = $component;
                    }

                    PageComponent::create([
                        'page_id' => $page->id,
                        'component_id' => $component->id,
                        'depth' => $callSite->depth,
                        'props_passed' => $callSite->propsPassed,
                    ]);
                }
            } catch (\Throwable $e) {
                $this->errors->error(ErrorLogger::PHASE_SCAN, 'Scanner '.$scanner::class." failed on page {$page->slug}: {$e->getMessage()}", [
                    'subject_type' => 'page',
                    'subject_id' => $page->id,
                ]);
            }
        }
    }

    private function viewportByLabel(string $label): array
    {
        foreach ((array) $this->config->get('bindle.viewports', []) as $viewport) {
            if (($viewport['label'] ?? '') === $label) {
                return $viewport;
            }
        }

        return [];
    }

    private function gitSha(): ?string
    {
        $head = base_path('.git/HEAD');
        if (! is_file($head)) {
            return null;
        }
        $content = trim((string) file_get_contents($head));
        if (str_starts_with($content, 'ref: ')) {
            $ref = base_path('.git/'.substr($content, 5));

            return is_file($ref) ? substr(trim((string) file_get_contents($ref)), 0, 12) : null;
        }

        return substr($content, 0, 12);
    }
}
