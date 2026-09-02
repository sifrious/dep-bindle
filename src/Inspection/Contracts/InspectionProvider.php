<?php

declare(strict_types=1);

namespace Maryeperry\Bindle\Inspection\Contracts;

use Maryeperry\Bindle\Inspection\Domain\InspectionRequest;
use Maryeperry\Bindle\Inspection\Domain\InspectionSnapshot;

interface InspectionProvider
{
    public function inspect(InspectionRequest $request): InspectionSnapshot;
}
