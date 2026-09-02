<?php

declare(strict_types=1);

namespace Maryeperry\Bindle\Inspection\Domain;

final readonly class SourceLocation
{
    public function __construct(
        public string $workspaceId,
        public string $relativePath,
        public int $startLine,
        public ?int $endLine = null,
        public ?string $url = null,
    ) {}
}
