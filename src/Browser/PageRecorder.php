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
        $firstResponse = null;

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
                if ($firstResponse === null) {
                    $firstResponse = $captured;
                    $html = $captured->html;
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

        if ($firstResponse !== null) {
            $this->flagSuspiciousResponse($route, $url, $firstResponse);
        }

        return new CapturedPage(
            url: $url,
            html: $html,
            screenshotPaths: $screenshots,
            htmlHash: hash('xxh3', $html),
        );
    }

    /**
     * Turn the two silent failure modes — an auth redirect to /login, or a
     * 4xx/5xx page rendered in place — into visible warnings. The screenshot
     * is still kept; this just stops the captured login/error page from
     * masquerading as the route's real content without anyone noticing.
     */
    private function flagSuspiciousResponse(ResolvedRoute $route, string $requestedUrl, CapturedResponse $response): void
    {
        $requestedPath = $this->pathOf($requestedUrl);
        $finalPath = $this->pathOf($response->finalUrl);

        if ($finalPath !== $requestedPath) {
            $loginPath = $this->pathOf((string) $this->config->get('bindle.auth.login_path', '/login'));

            $message = $finalPath === $loginPath
                ? "Route [{$route->identifier()}] redirected to the login page — it is behind auth and Bindle is not signed in. Set BINDLE_AUTH_USER_ID. The captured screenshot/DOM is the login page, not this route."
                : "Route [{$route->identifier()}] redirected from [{$requestedPath}] to [{$finalPath}] — the captured screenshot/DOM is the redirect target, not this route.";

            $this->errors->warn(ErrorLogger::PHASE_RENDER, $message, [
                'route' => $route->identifier(),
                'requested_url' => $requestedUrl,
                'final_url' => $response->finalUrl,
            ]);

            return;
        }

        if ($response->status !== null && ($response->status < 200 || $response->status >= 300)) {
            $this->errors->warn(ErrorLogger::PHASE_RENDER, "Route [{$route->identifier()}] returned HTTP {$response->status} — the captured screenshot/DOM is an error page, not this route's content.", [
                'route' => $route->identifier(),
                'url' => $requestedUrl,
                'status' => $response->status,
            ]);
        }
    }

    /**
     * Normalize a URL (or bare path) to a comparable path: no scheme/host,
     * no query/fragment, no trailing slash, leading slash guaranteed.
     */
    private function pathOf(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (! is_string($path) || $path === '') {
            $path = '/';
        }

        $path = '/'.ltrim($path, '/');

        return $path === '/' ? '/' : rtrim($path, '/');
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
