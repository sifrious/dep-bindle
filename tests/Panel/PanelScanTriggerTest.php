<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Maryeperry\Bindle\Browser\DriverKind;
use Maryeperry\Bindle\Http\ScanRunner;
use Maryeperry\Bindle\Storage\Database\DatabaseManager;
use Maryeperry\Bindle\Storage\Models\Run;
use Maryeperry\Bindle\Support\Environment;
use Tests\PanelTestCase;

beforeEach(function (): void {
    // A real file DB (not :memory:) so rows seeded here survive the controller
    // re-registering the connection — ensureSchema() purges, which wipes an
    // in-memory database.
    $this->dbPath = tempnam(sys_get_temp_dir(), 'bindle-panel-').'.sqlite';
    config()->set('bindle.database_path', $this->dbPath);

    Route::get('/hello', fn () => 'Hello World')->name('hello');

    // Replace the real runner so no subprocess is ever spawned in tests.
    $this->runner = Mockery::spy(ScanRunner::class);
    $this->app->instance(ScanRunner::class, $this->runner);
});

/**
 * POST through the panel with a valid CSRF token. The app boots as a "local"
 * (non-testing) environment for these tests, so Laravel enforces CSRF — we put
 * a matching token in both the session and the request, mirroring the @csrf
 * field the real Blade forms render.
 */
function bindlePanelPost(PanelTestCase $test, string $uri, array $data = [])
{
    return $test->withSession(['_token' => 'panel-test-token'])
        ->post($uri, array_merge(['_token' => 'panel-test-token'], $data));
}

afterEach(function (): void {
    if (isset($this->dbPath) && is_file($this->dbPath)) {
        @unlink($this->dbPath);
    }
});

it('triggers a fresh full scan and redirects to the status page', function (): void {
    bindlePanelPost($this, '/_bindle/scan')
        ->assertRedirect(route('bindle.panel.latest-status'));

    $this->runner->shouldHaveReceived('spawn')->with(null, true, DriverKind::Placeholder)->once();
});

it('triggers a single-page scan for a known route', function (): void {
    bindlePanelPost($this, '/_bindle/scan/page', ['route' => 'hello'])
        ->assertRedirect(route('bindle.panel.latest-status'));

    $this->runner->shouldHaveReceived('spawn')->with('hello', false, DriverKind::Placeholder)->once();
});

it('rejects a single-page scan for an unknown route', function (): void {
    bindlePanelPost($this, '/_bindle/scan/page', ['route' => 'nope'])
        ->assertRedirect(route('bindle.panel.index'))
        ->assertSessionHas('bindle_error');

    $this->runner->shouldNotHaveReceived('spawn');
});

it('renders a meta refresh while a run is in progress', function (): void {
    app(DatabaseManager::class)->ensureSchema();
    $run = Run::create(['environment' => 'local', 'status' => 'running', 'bindle_version' => 'test']);

    $this->get(route('bindle.panel.status', ['run' => $run->id]))
        ->assertOk()
        ->assertSee('http-equiv="refresh"', false);
});

it('omits the meta refresh once a run has completed', function (): void {
    app(DatabaseManager::class)->ensureSchema();
    $run = Run::create(['environment' => 'local', 'status' => 'completed', 'bindle_version' => 'test']);

    $this->get(route('bindle.panel.status', ['run' => $run->id]))
        ->assertOk()
        ->assertDontSee('http-equiv="refresh"', false);
});

it('refuses a dusk scan instead of quietly downgrading it to placeholders', function (): void {
    bindlePanelPost($this, '/_bindle/scan', ['driver' => 'dusk'])
        ->assertRedirect(route('bindle.panel.index'))
        ->assertSessionHas('bindle_error');

    $this->runner->shouldNotHaveReceived('spawn');
});

it('refuses a dusk single-page scan for the same reason', function (): void {
    bindlePanelPost($this, '/_bindle/scan/page', ['route' => 'hello', 'driver' => 'dusk'])
        ->assertRedirect(route('bindle.panel.index'))
        ->assertSessionHas('bindle_error');

    $this->runner->shouldNotHaveReceived('spawn');
});

it('says what is missing when it refuses real screenshots', function (): void {
    $response = bindlePanelPost($this, '/_bindle/scan', ['driver' => 'dusk']);

    expect(session('bindle_error'))->toContain('Real screenshots are not available');

    $response->assertRedirect(route('bindle.panel.index'));
});

it('shows the driver a run used on its status page', function (): void {
    app(DatabaseManager::class)->ensureSchema();
    $run = Run::create([
        'environment' => 'local',
        'status' => 'completed',
        'bindle_version' => 'test',
        'driver' => 'null',
    ]);

    $this->get(route('bindle.panel.status', ['run' => $run->id]))
        ->assertOk()
        ->assertSee('no screenshots')
        ->assertSee('1x1 placeholder');
});

it('labels a dusk run as carrying real screenshots', function (): void {
    app(DatabaseManager::class)->ensureSchema();
    $run = Run::create([
        'environment' => 'local',
        'status' => 'completed',
        'bindle_version' => 'test',
        'driver' => 'dusk',
    ]);

    $this->get(route('bindle.panel.status', ['run' => $run->id]))
        ->assertOk()
        ->assertSee('real screenshots');
});

it('warns on the panel that screenshots are placeholders', function (): void {
    $this->get('/_bindle')
        ->assertOk()
        ->assertSee('Screenshots are placeholders right now')
        ->assertSee('Run full scan — no screenshots');
});

it('lists the unmet dusk requirements with their fixes', function (): void {
    $this->get('/_bindle')
        ->assertOk()
        ->assertSee('php artisan dusk:install')
        ->assertSee('php artisan dusk:chrome-driver --detect')
        ->assertSee('composer require --dev laravel/dusk');
});

it('offers a driver dropdown on the scan form', function (): void {
    $this->get('/_bindle')
        ->assertOk()
        ->assertSee('name="driver"', false);
});

it('surfaces the launcher log on the status page', function (): void {
    $logPath = tempnam(sys_get_temp_dir(), 'bindle-log-').'.log';
    file_put_contents($logPath, "Cannot start a scan: no artisan file at [/nope/artisan].\n");
    config()->set('bindle.log_path', $logPath);

    $this->app->forgetInstance(ScanRunner::class);
    $this->app->instance(ScanRunner::class, new ScanRunner(
        app(Environment::class),
        config(),
    ));

    app(DatabaseManager::class)->ensureSchema();
    $run = Run::create([
        'environment' => 'local',
        'status' => 'completed',
        'bindle_version' => 'test',
        'driver' => 'null',
    ]);

    $this->get(route('bindle.panel.status', ['run' => $run->id]))
        ->assertOk()
        ->assertSee('Launcher log')
        ->assertSee('no artisan file');

    @unlink($logPath);
});
