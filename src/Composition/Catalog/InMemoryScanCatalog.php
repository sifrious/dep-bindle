<?php

declare(strict_types=1);

namespace Maryeperry\Bindle\Composition\Catalog;

final readonly class InMemoryScanCatalog implements ScanCatalog
{
    public function __construct(private CatalogSnapshot $catalog) {}

    public function snapshot(): CatalogSnapshot
    {
        return $this->catalog;
    }
}
