<?php

declare(strict_types=1);

namespace Maryeperry\Bindle\Browser;

/**
 * No-op browser driver. Writes a 1x1 placeholder PNG and returns whatever
 * HTML the caller has pre-stashed via withHtml(). Used in unit tests and
 * `bindle:scan --only=components|markdown` flows that don't need a real
 * browser.
 */
final class NullBrowserDriver implements BrowserDriver
{
    private string $stubHtml = '<html><body></body></html>';

    public function withHtml(string $html): self
    {
        $clone = clone $this;
        $clone->stubHtml = $html;

        return $clone;
    }

    public function capture(string $url, int $width, int $height, string $screenshotPath): string
    {
        // 1x1 transparent PNG so on-disk paths still resolve.
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkAAIAAAoAAv/lxKUAAAAASUVORK5CYII=');
        @file_put_contents($screenshotPath, $png);

        return $this->stubHtml;
    }
}
