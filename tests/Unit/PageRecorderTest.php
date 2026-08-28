<?php

declare(strict_types=1);

use Maryeperry\Bindle\Browser\BrowserDriver;
use Maryeperry\Bindle\Browser\CapturedResponse;
use Maryeperry\Bindle\Browser\DriverKind;
use Maryeperry\Bindle\Browser\PageRecorder;
use Maryeperry\Bindle\Routes\ResolvedRoute;
use Maryeperry\Bindle\Storage\Database\DatabaseManager;
use Maryeperry\Bindle\Storage\ErrorLogger;
use Maryeperry\Bindle\Storage\Models\ErrorLog;

/**
 * A driver that lands the browser on a URL of our choosing, so we can exercise
 * PageRecorder's redirect / non-2xx detection without a real browser.
 */
function fakeDriver(string $finalUrl, ?int $status = 200): BrowserDriver
{
    return new class($finalUrl, $status) implements BrowserDriver
    {
        public function __construct(private string $finalUrl, private ?int $status) {}

        public function kind(): DriverKind
        {
            return DriverKind::Placeholder;
        }

        public function capture(string $url, int $width, int $height, string $screenshotPath): CapturedResponse
        {
            @mkdir(dirname($screenshotPath), 0o755, true);
            @file_put_contents($screenshotPath, 'png');

            return new CapturedResponse('<html><body></body></html>', $this->finalUrl, $this->status);
        }
    };
}

function recordRoute(BrowserDriver $driver, string $uri = 'dashboard', ?string $name = 'dashboard'): void
{
    config()->set('bindle.output_path', sys_get_temp_dir().'/bindle-recorder-'.uniqid());
    config()->set('bindle.viewports', [['width' => 1440, 'height' => 900, 'label' => 'desktop']]);
    config()->set('app.url', 'http://localhost');

    app(DatabaseManager::class)->ensureSchema();

    $recorder = new PageRecorder(config(), $driver, app(ErrorLogger::class));

    $route = new ResolvedRoute(
        name: $name,
        uri: $uri,
        method: 'GET',
        controller: null,
        action: null,
        view: null,
        framework: 'blade',
        parameterNames: [],
        middleware: ['web', 'auth'],
    );

    $recorder->record($route);
}

it('warns when a route redirects to the login page', function (): void {
    recordRoute(fakeDriver('http://localhost/login'));

    $warning = ErrorLog::query()->where('severity', 'warn')->where('phase', 'render')->first();

    expect($warning)->not->toBeNull();
    expect($warning->message)->toContain('login page');
    expect($warning->message)->toContain('BINDLE_AUTH_USER_ID');
});

it('warns when a route redirects somewhere other than where requested', function (): void {
    recordRoute(fakeDriver('http://localhost/somewhere-else'));

    $warning = ErrorLog::query()->where('severity', 'warn')->where('phase', 'render')->first();

    expect($warning)->not->toBeNull();
    expect($warning->message)->toContain('redirected');
});

it('warns when a route returns a non-2xx status', function (): void {
    recordRoute(fakeDriver('http://localhost/dashboard', 403));

    $warning = ErrorLog::query()->where('severity', 'warn')->where('phase', 'render')->first();

    expect($warning)->not->toBeNull();
    expect($warning->message)->toContain('HTTP 403');
});

it('stays quiet when the browser lands exactly where requested with a 2xx', function (): void {
    recordRoute(fakeDriver('http://localhost/dashboard', 200));

    expect(ErrorLog::query()->where('severity', 'warn')->count())->toBe(0);
});

it('treats trailing-slash differences as the same path', function (): void {
    recordRoute(fakeDriver('http://localhost/dashboard/', 200));

    expect(ErrorLog::query()->where('severity', 'warn')->count())->toBe(0);
});
