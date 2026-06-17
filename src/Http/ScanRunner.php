<?php

declare(strict_types=1);

namespace Maryeperry\Bindle\Http;

use Maryeperry\Bindle\Support\Environment;
use Symfony\Component\Process\Process;

/**
 * Launches `php artisan bindle:scan` as a detached background process so the
 * web admin panel can trigger a scan and return immediately, then poll the
 * `runs` table for completion.
 *
 * The child is started through the shell with `nohup … &` so it survives the
 * web request ending — under php-fpm a plain Process::start() child can be
 * reaped when the worker finishes the request.
 */
class ScanRunner
{
    public function __construct(private readonly Environment $env) {}

    /**
     * Spawn a detached scan. Returns true once the launcher has been started.
     *
     * @param  string|null  $route  restrict the scan to a single route (name or URI)
     * @param  bool  $fresh  wipe prior run data before scanning
     */
    public function spawn(?string $route = null, bool $fresh = false): bool
    {
        // Guard #2: refuse to spawn anything when the environment isn't safe.
        $this->env->assertSafe();

        Process::fromShellCommandline($this->buildCommand($route, $fresh), base_path())->run();

        return true;
    }

    /**
     * Build the detached shell command. Exposed so tests can assert what would
     * be spawned without launching a real subprocess.
     */
    public function buildCommand(?string $route = null, bool $fresh = false): string
    {
        return sprintf(
            'nohup %s %s bindle:scan%s%s > /dev/null 2>&1 &',
            escapeshellarg(PHP_BINARY),
            escapeshellarg(base_path('artisan')),
            $fresh ? ' --fresh' : '',
            $route !== null ? ' --route='.escapeshellarg($route) : '',
        );
    }
}
