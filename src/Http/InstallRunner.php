<?php

declare(strict_types=1);

namespace Maryeperry\Bindle\Http;

use Maryeperry\Bindle\Browser\DriverAvailability;
use Maryeperry\Bindle\Support\Environment;
use Symfony\Component\Process\Process;

/**
 * Runs the Artisan installers the panel offers as buttons (dusk:install,
 * dusk:chrome-driver, bindle:install). Synchronous and output-capturing: the
 * developer needs to see why an install failed, not just that it did.
 */
final readonly class InstallRunner
{
    public function __construct(
        private Environment $env,
        private DriverAvailability $availability,
    ) {}

    /**
     * @return array{ok: bool, output: string}
     */
    public function run(string $action): array
    {
        $this->env->assertSafe();

        $command = $this->availability->commandFor($action);

        if ($command === null) {
            return ['ok' => false, 'output' => "Unknown install action [{$action}]."];
        }

        $arguments = [PHP_BINARY, base_path('artisan'), ...explode(' ', $command)];

        if ($action === DriverAvailability::ACTION_CHROME_DRIVER) {
            $arguments[] = '--detect';
        }

        $process = new Process($arguments, base_path(), timeout: 180.0);
        $process->run();

        return [
            'ok' => $process->isSuccessful(),
            'output' => trim($process->getOutput()."\n".$process->getErrorOutput()),
        ];
    }
}
