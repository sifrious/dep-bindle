<?php

declare(strict_types=1);

namespace Maryeperry\Bindle\Composition\Catalog;

interface ScanCatalog
{
    public function snapshot(): CatalogSnapshot;
}
