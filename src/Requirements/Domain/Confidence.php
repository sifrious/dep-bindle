<?php

declare(strict_types=1);

namespace Maryeperry\Bindle\Requirements\Domain;

/**
 * How sure the detector is that this requirement is real. Distinct from
 * EvidenceStrength, which grades a single source: a requirement can rest on one
 * strong source and still be a Medium call, and several weak sources can agree
 * into a High one.
 */
enum Confidence: string
{
    case High = 'high';

    case Medium = 'medium';

    case Low = 'low';

    public function label(): string
    {
        return $this->value;
    }
}
