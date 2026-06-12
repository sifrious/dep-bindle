<?php

declare(strict_types=1);

namespace Maryeperry\Bindle\Browser;

use Closure;
use Laravel\Dusk\Browser;

/**
 * Wraps a Dusk Browser instance. The actual Dusk lifecycle is owned by the
 * published `tests/Browser/BindleDuskTestCase` — that test passes a
 * `Closure(string $url): Browser` to this driver so we never have to manage
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
        /** @var Closure(): mixed */
        private readonly Closure $browserFactory,
    ) {}

    public function capture(string $url, int $width, int $height, string $screenshotPath): string
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

        $browser->screenshot(str_replace('.png', '', $screenshotPath));

        $html = (string) $browser->driver->getPageSource();

        return $html;
    }
}
