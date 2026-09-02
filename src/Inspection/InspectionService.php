<?php

declare(strict_types=1);

namespace Maryeperry\Bindle\Inspection;

use Maryeperry\Bindle\Inspection\Contracts\InspectionProvider;
use Maryeperry\Bindle\Inspection\Domain\InspectionRequest;
use Maryeperry\Bindle\Inspection\Domain\InspectionSnapshot;

final readonly class InspectionService
{
    public function __construct(private InspectionProvider $provider) {}

    /**
     * @param  iterable<InspectionRequest>  $requests
     * @return list<InspectionSnapshot>
     */
    public function inspect(iterable $requests): array
    {
        $snapshots = [];
        foreach ($requests as $request) {
            $snapshots[] = $this->provider->inspect($request);
        }

        return $snapshots;
    }
}
