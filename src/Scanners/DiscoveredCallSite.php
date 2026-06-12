<?php

declare(strict_types=1);

namespace Maryeperry\Bindle\Scanners;

final class DiscoveredCallSite
{
    /**
     * @param  array<string, string|int|float|bool|null>  $propsPassed
     */
    public function __construct(
        public readonly string $componentName,
        public readonly string $componentKind,
        public readonly array $propsPassed,
        public readonly ?string $parentComponentName = null,
        public readonly int $depth = 0,
    ) {}
}
