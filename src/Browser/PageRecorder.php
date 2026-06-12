<?php

declare(strict_types=1);

namespace Maryeperry\Bindle\Browser;

use Illuminate\Contracts\Config\Repository;
use Maryeperry\Bindle\Routes\ResolvedRoute;
use Maryeperry\Bindle\Storage\ErrorLogger;
use Maryeperry\Bindle\Support\Slug;

/**
 * PageRecorder drives the browser to capture (full-page screenshot + DOM)
 * for every enumerated route. The browser driver is injected so this class
 * is testable: pass a NullBrowserDriver in unit tests, a DuskBrowserDriver
 * in feature/Dusk tests.
 */
final class PageRecorder
{
    public function __construct(
        private readonly Repository $config,
        private readonly BrowserDriver $driver,
        private readonly ErrorLogger $errors,
    ) {}

    public function record(ResolvedRoute $route): ?CapturedPage
    {
        $url = $this->resolveUrl($route);
        if ($url === null) {
            $this->errors->warn(ErrorLogger::PHASE_RENDER, "Skipping route [{$route->identifier()}] — URL parameters could not be resolved.", [
                'route' => $route->identifier(),
                'parameters' => $route->parameterNames,
            ]);

            return null;
        }

        $viewports = (array) $this->config->get('bindle.viewports', []);
        $outputPath = (string) $this->config->get('bindle.output_path', '');
        $screenshots = [];
        $html = '';

        foreach ($viewports as $viewport) {
            $label = (string) ($viewport['label'] ?? 'default');
            $w = (int) ($viewport['width'] ?? 1440);
            $h = (int) ($viewport['height'] ?? 900);

            $slug = Slug::forRoute($route->name, $route->uri, $route->method);
            $dir = rtrim($outputPath, '/').'/pages/'.$slug;
            if (! is_dir($dir)) {
                @mkdir($dir, 0o755, true);
            }

            $screenshotPath = $dir.'/'.$slug.'-detail-'.$label.'.png';

            try {
                $captured = $this->driver->capture($url, $w, $h, $screenshotPath);
                $screenshots[$label] = $screenshotPath;
                if ($html === '') {
                    $html = $captured;
                }
            } catch (\Throwable $e) {
                $this->errors->error(ErrorLogger::PHASE_RENDER, "Failed to capture [{$url}] at viewport [{$label}]: {$e->getMessage()}", [
                    'route' => $route->identifier(),
                    'viewport' => $label,
                ]);
            }
        }

        if ($screenshots === []) {
            return null;
        }

        return new CapturedPage(
            url: $url,
            html: $html,
            screenshotPaths: $screenshots,
            htmlHash: hash('xxh3', $html),
        );
    }

    private function resolveUrl(ResolvedRoute $route): ?string
    {
        $appUrl = rtrim((string) $this->config->get('app.url', 'http://localhost'), '/');

        if (! $route->hasParameters()) {
            return $appUrl.'/'.ltrim($route->uri, '/');
        }

        $fixtures = (array) $this->config->get('bindle.fixtures', []);
        $key = $route->name ?? $route->uri;
        $params = $fixtures[$key] ?? null;

        if (! is_array($params)) {
            return null;
        }

        $uri = $route->uri;
        foreach ($params as $name => $value) {
            $uri = preg_replace('/\\{'.preg_quote((string) $name, '/').'\\??\\}/', (string) $value, $uri) ?? $uri;
        }
        if (str_contains($uri, '{')) {
            return null;
        }

        return $appUrl.'/'.ltrim($uri, '/');
    }
}
