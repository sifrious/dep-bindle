<?php

declare(strict_types=1);

namespace Maryeperry\Bindle\Composition\Planning;

use Maryeperry\Bindle\Composition\Catalog\CatalogSnapshot;
use Maryeperry\Bindle\Composition\Contracts\PageComposition;

final class ReuseFirstRealizationPlanner
{
    public function plan(PageComposition $composition, CatalogSnapshot $catalog): RealizationPlan
    {
        $document = $composition->toArray();
        $framework = is_string($document['framework'] ?? null) ? $document['framework'] : 'blade';
        $paths = array_values(array_filter(is_array($document['paths'] ?? null) ? $document['paths'] : [], is_string(...)));
        $documentRegions = $document['regions'] ?? [];
        $unresolved = [];

        $regions = [];
        foreach (is_array($documentRegions) ? $documentRegions : [] as $position => $region) {
            if (! is_array($region)) {
                continue;
            }
            $ref = $region['ref'] ?? [];
            if (! is_array($ref)) {
                continue;
            }
            if (! is_string($ref['id'] ?? null)) {
                continue;
            }
            if (! is_string($ref['status'] ?? null)) {
                continue;
            }
            $reference = $ref['id'];
            $component = $catalog->component($reference);
            $requestedStatus = $ref['status'];
            $status = $component === null ? 'proposal' : 'reuse';
            $id = is_string($region['id'] ?? null) ? $region['id'] : (string) $position;
            $behaviors = array_values(array_filter(is_array($region['behaviors'] ?? null) ? $region['behaviors'] : [], is_string(...)));
            $regions[] = [
                'id' => $id,
                'component_ref' => $reference,
                'status' => $status,
                'framework' => $component === null
                    ? $framework
                    : (in_array($component['kind'] ?? null, ['blade', 'livewire'], true) ? $component['kind'] : 'blade'),
                'source_path' => $component['source_path'] ?? null,
                'behaviors' => $behaviors,
            ];
            if ($requestedStatus !== $status) {
                $unresolved[] = "region {$id} requested {$requestedStatus} for {$reference}, but catalog requires {$status}";
            }
        }

        usort($regions, static fn (array $left, array $right): int => strcmp($left['id'], $right['id']));
        sort($unresolved);

        return new RealizationPlan($framework, $paths, $regions, $unresolved);
    }
}
