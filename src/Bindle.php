<?php

declare(strict_types=1);

namespace Maryeperry\Bindle;

use Maryeperry\Bindle\Pipeline\ScanPipeline;
use Maryeperry\Bindle\Storage\Database\DatabaseManager;
use Maryeperry\Bindle\Support\Environment;

final class Bindle
{
    public function __construct(
        public readonly Environment $environment,
        public readonly DatabaseManager $database,
        public readonly ScanPipeline $pipeline,
    ) {}

    public function version(): string
    {
        return '0.1.0-dev';
    }
}
