<?php

declare(strict_types=1);

namespace Maryeperry\Bindle\Inspection\Domain;

final readonly class CodeSymbol
{
    /** @param list<self> $children */
    public function __construct(
        public string $kind,
        public string $name,
        public SourceLocation $source,
        public ?string $signature = null,
        public array $children = [],
    ) {}
}
