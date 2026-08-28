<?php

declare(strict_types=1);

namespace Maryeperry\Bindle\Http;

use Illuminate\Contracts\Config\Repository;
use Maryeperry\Bindle\Browser\DriverKind;
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
 *
 * Its output goes to a log file rather than /dev/null: a scan that dies before
 * it can write a `runs` row would otherwise leave the panel showing nothing at
 * all, with no way to find out why.
 */
class ScanRunner
{
    public function __construct(
        private readonly Environment $env,
        private readonly Repository $config,
    ) {}

    /**
     * Spawn a detached scan. Returns true once the launcher has been started.
     *
     * @param  string|null  $route  restrict the scan to a single route (name or URI)
     * @param  bool  $fresh  wipe prior run data before scanning
     * @param  DriverKind  $driver  which browser to capture with
     */
    public function spawn(?string $route = null, bool $fresh = false, DriverKind $driver = DriverKind::Placeholder): bool
    {
        $this->env->assertSafe();

        $artisan = $this->artisanPath();

        if (! is_file($artisan)) {
            $this->appendLog("Cannot start a scan: no artisan file at [{$artisan}].");

            return false;
        }

        $this->prepareLog($route, $driver);

        Process::fromShellCommandline($this->buildCommand($route, $fresh, $driver), base_path())->run();

        return true;
    }

    /**
     * Build the detached shell command. Exposed so tests can assert what would
     * be spawned without launching a real subprocess.
     */
    public function buildCommand(?string $route = null, bool $fresh = false, DriverKind $driver = DriverKind::Placeholder): string
    {
        return sprintf(
            'nohup %s %s bindle:scan --driver=%s%s%s >> %s 2>&1 &',
            escapeshellarg(PHP_BINARY),
            escapeshellarg($this->artisanPath()),
            escapeshellarg($driver->value),
            $fresh ? ' --fresh' : '',
            $route !== null ? ' --route='.escapeshellarg($route) : '',
            escapeshellarg($this->logPath()),
        );
    }

    public function logPath(): string
    {
        return (string) $this->config->get('bindle.log_path', base_path('.bindle/scan.log'));
    }

    /**
     * The last lines of the scan log, for the panel to show when a run failed
     * to appear or finished badly.
     */
    public function tailLog(int $lines = 40): string
    {
        $path = $this->logPath();

        if (! is_file($path)) {
            return '';
        }

        $contents = (string) file_get_contents($path);
        $all = preg_split('/\R/', rtrim($contents)) ?: [];

        return implode("\n", array_slice($all, -$lines));
    }

    private function prepareLog(?string $route, DriverKind $driver): void
    {
        $this->appendLog(sprintf(
            'Spawning scan (driver=%s, route=%s) from the panel.',
            $driver->value,
            $route ?? 'all',
        ));
    }

    private function appendLog(string $message): void
    {
        $path = $this->logPath();
        $dir = dirname($path);

        if (! is_dir($dir)) {
            @mkdir($dir, 0o755, true);
        }

        @file_put_contents(
            $path,
            '['.date('Y-m-d H:i:s').'] '.$message.PHP_EOL,
            FILE_APPEND,
        );
    }

    private function artisanPath(): string
    {
        return base_path('artisan');
    }
}
