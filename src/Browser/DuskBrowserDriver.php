<?php

declare(strict_types=1);

namespace Maryeperry\Bindle\Browser;

use Closure;
use Laravel\Dusk\Browser;

/**
 * Wraps a Dusk Browser instance. The actual Dusk lifecycle is owned by the
 * published `tests/Browser/BindleScanTest` — that test passes a
 * `Closure(): Browser` to this driver so we never have to manage
 * ChromeDriver from inside the package itself.
 *
 * Capture flow:
 *   1. browser->resize(width, height)
 *   2. browser->visit(url)
 *   3. scroll-to-bottom helper (load any lazy content)
 *   4. resize to full page height + screenshot
 *   5. capture browser->driver->getPageSource()
 */
final class DuskBrowserDriver implements BrowserDriver
{
    public function __construct(
        /** @var Closure(): Browser */
        private readonly Closure $browserFactory,
    ) {}

    public function kind(): DriverKind
    {
        return DriverKind::Dusk;
    }

    public function capture(string $url, int $width, int $height, string $screenshotPath): CapturedResponse
    {
        /** @var Browser $browser */
        $browser = ($this->browserFactory)();

        $browser->resize($width, $height);
        $browser->visit($url);
        $browser->pause(250);

        $browser->script('window.scrollTo(0, document.body.scrollHeight);');
        $browser->pause(150);
        $browser->script('window.scrollTo(0, 0);');

        $fullHeight = (int) $browser->script('return document.body.scrollHeight;')[0];
        if ($fullHeight > $height) {
            $browser->resize($width, $fullHeight);
            $browser->pause(150);
        }

        // Write straight to Bindle's output path. We deliberately bypass
        // Browser::screenshot(), which prefixes Dusk's own screenshot
        // directory and would mangle the absolute path PageRecorder builds.
        $dir = dirname($screenshotPath);
        if (! is_dir($dir)) {
            @mkdir($dir, 0o755, true);
        }
        $browser->driver->takeScreenshot($screenshotPath);

        $html = (string) $browser->driver->getPageSource();
        $finalUrl = (string) $browser->driver->getCurrentURL();

        // Best-effort HTTP status via the Navigation Timing API (Chrome 109+).
        // Catches 4xx/5xx pages rendered in place; null when unavailable.
        // Auth redirects collapse to a 200 here, so PageRecorder also compares
        // the final URL against the requested one to spot those.
        $status = null;
        try {
            $raw = $browser->script(
                "return (performance.getEntriesByType('navigation')[0] || {}).responseStatus ?? null;"
            );
            if (isset($raw[0]) && is_int($raw[0]) && $raw[0] > 0) {
                $status = $raw[0];
            }
        } catch (\Throwable) {
            // Older Chrome / API unavailable — leave status null.
        }

        return new CapturedResponse($html, $finalUrl, $status);
    }
}
