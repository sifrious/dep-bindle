<?php

declare(strict_types=1);

namespace Maryeperry\Bindle\Support;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Maryeperry\Bindle\Exceptions\ProductionForbiddenException;

final class Environment
{
    public function __construct(
        private readonly Application $app,
        private readonly Repository $config,
    ) {}

    public function assertSafe(): void
    {
        $env = (string) ($this->config->get('app.env') ?? $this->app->environment());

        if ($env === 'production' || $this->app->environment('production')) {
            throw ProductionForbiddenException::forEnvironment($env);
        }

        if (! (bool) $this->config->get('bindle.enabled', false)) {
            throw ProductionForbiddenException::forDisabledConfig();
        }

        $pattern = (string) $this->config->get('bindle.production_host_pattern', '');
        if ($pattern !== '') {
            $host = (string) parse_url((string) $this->config->get('app.url', ''), PHP_URL_HOST);
            if ($host !== '' && @preg_match($pattern, $host) === 1) {
                throw ProductionForbiddenException::forProductionHost($host);
            }
        }
    }

    public function isSafe(): bool
    {
        try {
            $this->assertSafe();

            return true;
        } catch (ProductionForbiddenException) {
            return false;
        }
    }
}
