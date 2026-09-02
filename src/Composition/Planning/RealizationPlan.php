<?php

declare(strict_types=1);

namespace Maryeperry\Bindle\Composition\Planning;

final readonly class RealizationPlan
{
    /**
     * @param  list<string>  $paths
     * @param  list<array<string, mixed>>  $regions
     * @param  list<string>  $unresolved
     */
    public function __construct(
        public string $framework,
        public array $paths,
        public array $regions,
        public array $unresolved = [],
    ) {}

    /** @return array{framework: string, paths: list<string>, regions: list<array<string, mixed>>, unresolved: list<string>} */
    public function toArray(): array
    {
        return ['framework' => $this->framework, 'paths' => $this->paths, 'regions' => $this->regions, 'unresolved' => $this->unresolved];
    }
}
