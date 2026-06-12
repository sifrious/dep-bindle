<?php

declare(strict_types=1);

namespace Maryeperry\Bindle\Exceptions;

use RuntimeException;

final class ProductionForbiddenException extends RuntimeException
{
    public static function forEnvironment(string $env): self
    {
        return new self(
            "Bindle refuses to run in environment [{$env}]. Bindle is a local/dev-only tool and cannot be used against a production application."
        );
    }

    public static function forDisabledConfig(): self
    {
        return new self(
            'Bindle is disabled via config (bindle.enabled=false). It will not run.'
        );
    }

    public static function forProductionHost(string $host): self
    {
        return new self(
            "Bindle refuses to run against host [{$host}], which matches the configured production host pattern."
        );
    }
}
