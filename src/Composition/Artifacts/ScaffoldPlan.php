<?php

declare(strict_types=1);

namespace Maryeperry\Bindle\Composition\Artifacts;

final readonly class ScaffoldPlan
{
    /** @param list<GeneratedArtifact> $artifacts */
    public function __construct(
        public array $artifacts,
        public string $patch,
        public bool $dryRun,
        public ?string $manifestPath = null,
    ) {}
}
