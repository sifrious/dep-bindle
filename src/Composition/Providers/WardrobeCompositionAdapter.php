<?php

declare(strict_types=1);

namespace Maryeperry\Bindle\Composition\Providers;

use Closure;
use Maryeperry\Bindle\Composition\Catalog\CatalogSnapshot;
use Maryeperry\Bindle\Composition\Contracts\BehaviorStory;
use Maryeperry\Bindle\Composition\Contracts\PageComposition;

final readonly class WardrobeCompositionAdapter implements CompositionProvider
{
    /** @param Closure(array<string, mixed>, array<string, mixed>): array<string, mixed> $invoke */
    public function __construct(private Closure $invoke) {}

    public function compose(BehaviorStory $story, CatalogSnapshot $catalog): PageComposition
    {
        $document = ($this->invoke)($story->toArray(), $catalog->toArray());
        $composition = PageComposition::fromArray($document, $story);

        $regions = $document['regions'] ?? [];
        foreach (is_array($regions) ? $regions : [] as $region) {
            if (! is_array($region)) {
                continue;
            }
            if (! is_array($region['ref'] ?? null)) {
                continue;
            }
            $reference = $region['ref']['id'] ?? null;
            $status = $region['ref']['status'] ?? null;
            if (! is_string($reference)) {
                continue;
            }
            if (! is_string($status)) {
                continue;
            }
            $known = $catalog->hasReference($reference);
            if ($status === 'reuse' && ! $known) {
                throw new InvalidProviderComposition("Provider returned unknown reuse reference: {$reference}.");
            }
            if ($status === 'proposal' && $known) {
                throw new InvalidProviderComposition("Provider proposed existing catalog reference: {$reference}.");
            }
        }

        return $composition;
    }
}
