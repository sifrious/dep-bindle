<?php

declare(strict_types=1);

namespace Maryeperry\Bindle\Browser;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Laravel\Dusk\Browser;
use Maryeperry\Bindle\Support\UrlProbe;

/**
 * Probes whether a driver can actually run here, so the panel can offer real
 * screenshots when they are possible and say exactly what is missing when they
 * are not, instead of silently falling back to placeholders.
 */
final readonly class DriverAvailability
{
    public const string ACTION_DUSK_INSTALL = 'dusk-install';

    public const string ACTION_CHROME_DRIVER = 'chrome-driver';

    public const string ACTION_BINDLE_INSTALL = 'bindle-install';

    public function __construct(
        private Application $app,
        private Repository $config,
        private UrlProbe $probe,
    ) {}

    /**
     * @return list<DriverRequirement>
     */
    public function requirements(DriverKind $kind): array
    {
        if ($kind === DriverKind::Placeholder) {
            return [];
        }

        return [
            $this->duskPackage(),
            $this->duskTestCase(),
            $this->scanTest(),
            $this->chromeDriver(),
            $this->duskEnvironment(),
            $this->appReachable(),
        ];
    }

    public function isAvailable(DriverKind $kind): bool
    {
        foreach ($this->requirements($kind) as $requirement) {
            if (! $requirement->satisfied) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<DriverRequirement>
     */
    public function unmet(DriverKind $kind): array
    {
        return array_values(array_filter(
            $this->requirements($kind),
            static fn (DriverRequirement $requirement): bool => ! $requirement->satisfied,
        ));
    }

    /**
     * @return list<DriverKind>
     */
    public function availableKinds(): array
    {
        $kinds = [DriverKind::Placeholder];

        if ($this->isAvailable(DriverKind::Dusk)) {
            $kinds[] = DriverKind::Dusk;
        }

        return $kinds;
    }

    public function commandFor(string $action): ?string
    {
        return match ($action) {
            self::ACTION_DUSK_INSTALL => 'dusk:install',
            self::ACTION_CHROME_DRIVER => 'dusk:chrome-driver',
            self::ACTION_BINDLE_INSTALL => 'bindle:install',
            default => null,
        };
    }

    private function duskPackage(): DriverRequirement
    {
        return new DriverRequirement(
            key: 'dusk-package',
            label: 'laravel/dusk is installed',
            satisfied: class_exists(Browser::class),
            consequence: 'Without Dusk there is no way to drive a real browser, so screenshots stay placeholders.',
            command: 'composer require --dev laravel/dusk',
            detail: 'Composer cannot be run from this panel — run it in your terminal.',
        );
    }

    private function duskTestCase(): DriverRequirement
    {
        return new DriverRequirement(
            key: 'dusk-testcase',
            label: 'tests/DuskTestCase.php exists',
            satisfied: is_file($this->basePath('tests/DuskTestCase.php')),
            consequence: 'BindleScanTest extends this class; without it the scan test cannot boot.',
            command: 'php artisan dusk:install',
            action: self::ACTION_DUSK_INSTALL,
        );
    }

    private function scanTest(): DriverRequirement
    {
        return new DriverRequirement(
            key: 'scan-test',
            label: 'tests/Browser/BindleScanTest.php is published',
            satisfied: is_file($this->basePath('tests/Browser/BindleScanTest.php')),
            consequence: 'This published test is what actually performs a Dusk scan.',
            command: 'php artisan bindle:install',
            action: self::ACTION_BINDLE_INSTALL,
        );
    }

    private function chromeDriver(): DriverRequirement
    {
        $binaries = glob($this->basePath('vendor/laravel/dusk/bin/chromedriver-*')) ?: [];

        return new DriverRequirement(
            key: 'chrome-driver',
            label: 'A ChromeDriver binary is present',
            satisfied: $binaries !== [],
            consequence: 'Dusk cannot start Chrome, and every page capture fails.',
            command: 'php artisan dusk:chrome-driver --detect',
            action: self::ACTION_CHROME_DRIVER,
            detail: 'You also need Google Chrome itself installed — the driver is not the browser.',
        );
    }

    private function duskEnvironment(): DriverRequirement
    {
        return new DriverRequirement(
            key: 'dusk-env',
            label: '.env.dusk.local points Dusk at a local URL',
            satisfied: is_file($this->basePath('.env.dusk.local')),
            consequence: 'Without it Dusk falls back to your normal .env — if that APP_URL is production, the scan would crawl production.',
            command: 'cp .env .env.dusk.local   # then set APP_URL=http://127.0.0.1:8000',
            detail: 'Dusk replaces the whole env file, so this must be a full copy, not a one-line override.',
        );
    }

    private function appReachable(): DriverRequirement
    {
        $url = (string) $this->config->get('app.url', '');
        $reason = $url === '' ? 'app.url is empty.' : $this->probe->unreachableReason($url);

        return new DriverRequirement(
            key: 'app-serving',
            label: 'The app is reachable at '.($url === '' ? 'app.url' : $url),
            satisfied: $reason === null,
            consequence: 'Dusk visits real URLs. With nothing serving, every route captures a connection error.',
            command: 'php artisan serve',
            detail: $reason,
        );
    }

    private function basePath(string $path): string
    {
        return rtrim($this->app->basePath(), '/').'/'.ltrim($path, '/');
    }
}
