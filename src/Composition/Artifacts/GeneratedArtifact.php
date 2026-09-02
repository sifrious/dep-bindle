<?php

declare(strict_types=1);

namespace Maryeperry\Bindle\Composition\Artifacts;

final readonly class GeneratedArtifact
{
    public function __construct(
        public string $path,
        public string $contents,
    ) {}
}
