<?php

declare(strict_types=1);

namespace Maryeperry\Bindle\Browser;

interface BrowserDriver
{
    /**
     * Visit the URL at the given viewport, write a full-page screenshot to
     * $screenshotPath, and return the rendered HTML.
     *
     * Implementations: DuskBrowserDriver (production-quality, requires
     * Chrome), NullBrowserDriver (test/no-op).
     */
    public function capture(string $url, int $width, int $height, string $screenshotPath): string;
}
