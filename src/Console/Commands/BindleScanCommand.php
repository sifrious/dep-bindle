<?php

declare(strict_types=1);

namespace Maryeperry\Bindle\Console\Commands;

use Illuminate\Console\Command;
use Maryeperry\Bindle\Browser\NullBrowserDriver;
use Maryeperry\Bindle\Pipeline\ScanPipeline;
use Maryeperry\Bindle\Support\Environment;

final class BindleScanCommand extends Command
{
    protected $signature = 'bindle:scan
                            {--only= : Limit phases: comma-separated routes,screenshots,components,markdown}
                            {--fresh : Wipe prior run data before scanning}
                            {--driver=null : null|dusk — which browser driver to use}';

    protected $description = 'Run a Bindle scan: enumerate routes, screenshot pages, discover components, emit Markdown.';

    public function handle(Environment $env, ScanPipeline $pipeline): int
    {
        $env->assertSafe();

        $only = array_values(array_filter(array_map('trim', explode(',', (string) $this->option('only')))));

        $driver = match ($this->option('driver')) {
            'dusk' => throw new \RuntimeException(
                'The dusk driver must be invoked from inside a `php artisan dusk` run. See tests/Browser/BindleDuskTestCase.'
            ),
            default => new NullBrowserDriver,
        };

        $this->info('Starting Bindle scan...');
        $run = $pipeline->run($driver, $only, (bool) $this->option('fresh'));
        $this->info("Bindle scan completed (run #{$run->id}). Output: ".config('bindle.output_path'));

        return self::SUCCESS;
    }
}
