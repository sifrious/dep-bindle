<?php

declare(strict_types=1);

namespace Maryeperry\Bindle\Composition\Providers;

use Maryeperry\Bindle\Composition\Catalog\CatalogSnapshot;
use Maryeperry\Bindle\Composition\Contracts\BehaviorStory;
use Maryeperry\Bindle\Composition\Contracts\PageComposition;

interface CompositionProvider
{
    public function compose(BehaviorStory $story, CatalogSnapshot $catalog): PageComposition;
}
