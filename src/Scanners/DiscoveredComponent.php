<?php

declare(strict_types=1);

namespace Maryeperry\Bindle\Scanners;

final class DiscoveredComponent
{
    /**
     * @param  list<DiscoveredProp>  $props
     */
    public function __construct(
        public readonly string $name,
        public readonly string $kind,
        public readonly ?string $sourcePath,
        public readonly array $props,
        public readonly ?string $signatureHash = null,
    ) {}
}
