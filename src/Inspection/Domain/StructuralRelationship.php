<?php

declare(strict_types=1);

namespace Maryeperry\Bindle\Inspection\Domain;

final readonly class StructuralRelationship
{
    public function __construct(
        public string $from,
        public string $type,
        public string $to,
        public SourceLocation $evidence,
    ) {}
}
