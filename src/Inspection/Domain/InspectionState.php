<?php

declare(strict_types=1);

namespace Maryeperry\Bindle\Inspection\Domain;

enum InspectionState: string
{
    case Available = 'available';
    case Empty = 'empty';
    case Stale = 'stale';
    case Unavailable = 'unavailable';
}
