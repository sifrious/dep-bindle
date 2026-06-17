<?php

declare(strict_types=1);

namespace Maryeperry\Bindle\Console\Commands;

use Illuminate\Console\Command;
use Maryeperry\Bindle\Browser\NullBrowserDriver;
use Maryeperry\Bindle\Pipeline\ScanPipeline;
use Maryeperry\Bindle\Support\Environment;
use Symfony\Component\Process\Process;

final class BindleScanCommand extends Command
{
    protected $signature = 'bindle:scan
                            {--only= : Limit phases: comma-separated routes,screenshots,components,markdown}
                            {--route= : Scan only the route with this name or URI}
                            {--fresh : Wipe prior run data before scanning}
                            {--driver=null : null|dusk — which browser driver to use}';

    protected $description = 'Run a Bindle scan: enumerate routes, screenshot pages, discover components, emit Markdown.';

    public function handle(Environment $env, ScanPipeline $pipeline): int
    {
        // Real screenshots require Chrome, which Bindle drives through Laravel
        // Dusk. Dusk owns its own bootstrapping/test environment, so we hand
        // the whole scan off to the published Dusk test rather than trying to
        // run the pipeline inline with a browser.
        //
        // We deliberately DON'T assertSafe() here for the Dusk path: this
        // parent process loads the normal `.env` (which may legitimately point
        // APP_URL at production), but the Dusk subprocess runs against
        // `.env.dusk.local`. The subprocess runs its own assertSafe() against
        // the URL it actually visits, so the production-host guard is enforced
        // in the right place instead of false-tripping on the parent's URL.
        if ($this->option('driver') === 'dusk') {
            return $this->runViaDusk();
        }

        // The null driver runs inline against this process's environment, so
        // the safety guard applies directly here.
        $env->assertSafe();

        $only = array_values(array_filter(array_map('trim', explode(',', (string) $this->option('only')))));

        $route = $this->option('route');
        $route = $route !== null && $route !== '' ? (string) $route : null;

        $this->info('Starting Bindle scan (null driver — no real screenshots)...');
        $run = $pipeline->run(new NullBrowserDriver, $only, (bool) $this->option('fresh'), $route);
        $this->info("Bindle scan completed (run #{$run->id}). Output: ".config('bindle.output_path'));

        return self::SUCCESS;
    }

    private function runViaDusk(): int
    {
        $relativeTestPath = 'tests/Browser/BindleScanTest.php';

        if (! is_file(base_path($relativeTestPath))) {
            $this->error("{$relativeTestPath} not found. Run `php artisan bindle:install` to publish it.");

            return self::FAILURE;
        }

        $this->info('Running Bindle scan through Laravel Dusk (real Chrome)...');
        $this->line('Dusk uses the APP_URL in your .env.dusk.local — make sure that app is');
        $this->line('reachable (e.g. `php artisan serve`) and ChromeDriver is installed');
        $this->line('(`php artisan dusk:chrome-driver --detect`).');
        $this->newLine();

        // Spawn a fresh `php artisan dusk <path>` process. We avoid $this->call('dusk')
        // because Dusk reads its arguments straight from $_SERVER['argv'], which would
        // carry this command's flags (e.g. --driver=dusk) into PHPUnit.
        $process = new Process(
            [PHP_BINARY, base_path('artisan'), 'dusk', $relativeTestPath],
            base_path(),
            timeout: null,
        );

        try {
            $process->setTty(Process::isTtySupported());
        } catch (\Throwable) {
            // No TTY (CI etc.) — fall back to piped output below.
        }

        $exitCode = $process->run(function (string $type, string $buffer): void {
            $this->output->write($buffer);
        });

        return $exitCode === 0 ? self::SUCCESS : self::FAILURE;
    }
}
