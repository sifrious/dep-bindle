<?php

declare(strict_types=1);

namespace Maryeperry\Bindle\Browser;

final class CapturedPage
{
    /**
     * @param  array<string, string>  $screenshotPaths  viewport_label => filesystem path
     */
    public function __construct(
        public readonly string $url,
        public readonly string $html,
        public readonly array $screenshotPaths,
        public readonly string $htmlHash,
    ) {}
}
