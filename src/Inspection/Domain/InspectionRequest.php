<?php

declare(strict_types=1);

namespace Maryeperry\Bindle\Inspection\Domain;

final readonly class InspectionRequest
{
    public function __construct(
        public string $workspaceId,
        public string $rootPath,
        public ?string $relativePath = null,
        public ?string $revision = null,
    ) {}
}
