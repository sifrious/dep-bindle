<?php

declare(strict_types=1);

namespace Maryeperry\Bindle\Console\Commands;

use Illuminate\Console\Command;
use Maryeperry\Bindle\Browser\DriverAvailability;
use Maryeperry\Bindle\Browser\DriverKind;
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

    public function handle(Environment $env, ScanPipeline $pipeline, DriverAvailability $availability): int
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
        if (DriverKind::fromOption($this->option('driver')) === DriverKind::Dusk) {
            return $this->runViaDusk($availability);
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

    /**
     * Turn the common ChromeDriver/connection failures into one actionable
     * line each, instead of leaving the developer to read a PHPUnit stack.
     *
     * @return list<string>
     */
    private function diagnose(string $output): array
    {
        $hints = [];

        $patterns = [
            'cannot find Chrome binary' => 'Chrome itself is not installed (the driver is not the browser). On macOS: brew install --cask google-chrome',
            'session not created' => 'ChromeDriver and Chrome versions disagree. Re-run: php artisan dusk:chrome-driver --detect',
            'Connection refused' => 'Nothing is serving your app. Start it with `php artisan serve` and check APP_URL in .env.dusk.local.',
            'ERR_CONNECTION_REFUSED' => 'The browser could not reach APP_URL. Start the app and check .env.dusk.local.',
            'chromedriver' => 'ChromeDriver may be missing or unexecutable. Re-run: php artisan dusk:chrome-driver --detect',
            'Class "Laravel\\Dusk' => 'laravel/dusk is not installed. Run: composer require --dev laravel/dusk',
        ];

        foreach ($patterns as $needle => $hint) {
            if (stripos($output, $needle) !== false) {
                $hints[$hint] = true;
            }
        }

        if ($hints === []) {
            $hints['Check the Dusk output above, and `php artisan bindle:errors` for per-route failures.'] = true;
        }

        return array_keys($hints);
    }

    private function runViaDusk(DriverAvailability $availability): int
    {
        $relativeTestPath = 'tests/Browser/BindleScanTest.php';

        $unmet = $availability->unmet(DriverKind::Dusk);

        if ($unmet !== []) {
            $this->error('Cannot take real screenshots yet. Missing preconditions:');
            $this->newLine();

            foreach ($unmet as $requirement) {
                $this->line("  <fg=red>x</> {$requirement->label}");
                $this->line("    why: {$requirement->consequence}");

                if ($requirement->detail !== null) {
                    $this->line("    note: {$requirement->detail}");
                }

                $this->line("    fix: {$requirement->command}");
                $this->newLine();
            }

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

        $captured = '';
        $exitCode = $process->run(function (string $type, string $buffer) use (&$captured): void {
            $captured .= $buffer;
            $this->output->write($buffer);
        });

        if ($exitCode !== 0) {
            $this->newLine();
            $this->error("Dusk exited with code {$exitCode} — no real screenshots were captured.");

            foreach ($this->diagnose($captured) as $hint) {
                $this->line("  <fg=yellow>-</> {$hint}");
            }

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
