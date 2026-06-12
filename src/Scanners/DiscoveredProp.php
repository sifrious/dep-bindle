<?php

declare(strict_types=1);

namespace Maryeperry\Bindle\Scanners;

final class DiscoveredProp
{
    public function __construct(
        public readonly string $name,
        public readonly ?string $type,
        public readonly bool $required,
        public readonly ?string $defaultValue,
        public readonly string $source, // 'props-directive' | 'public-property' | 'define-props' | 'prop-types' | 'jsx-arg' | 'svelte-export'
    ) {}
}
