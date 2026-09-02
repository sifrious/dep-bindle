<?php

declare(strict_types=1);

namespace Maryeperry\Bindle\Inspection\Domain;

final readonly class DiscoveredResource
{
    public function __construct(public string $kind, public SourceLocation $source) {}
}
