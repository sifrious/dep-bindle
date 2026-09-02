<?php

declare(strict_types=1);

namespace Maryeperry\Bindle\Inspection\Domain;

use DateTimeImmutable;

final readonly class InspectionSnapshot
{
    /**
     * @param  list<CodeSymbol>  $symbols
     * @param  list<DiscoveredResource>  $resources
     * @param  list<StructuralRelationship>  $relationships
     */
    public function __construct(
        public string $workspaceId,
        public string $scope,
        public InspectionState $state,
        public DateTimeImmutable $inspectedAt,
        public array $symbols = [],
        public array $resources = [],
        public array $relationships = [],
        public ?string $message = null,
        public bool $partial = false,
        public ?string $revision = null,
    ) {}
}
