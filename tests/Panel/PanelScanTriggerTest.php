<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Maryeperry\Bindle\Http\ScanRunner;
use Maryeperry\Bindle\Storage\Database\DatabaseManager;
use Maryeperry\Bindle\Storage\Models\Run;
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

    $this->runner->shouldHaveReceived('spawn')->with(null, true)->once();
});

it('triggers a single-page scan for a known route', function (): void {
    bindlePanelPost($this, '/_bindle/scan/page', ['route' => 'hello'])
        ->assertRedirect(route('bindle.panel.latest-status'));

    $this->runner->shouldHaveReceived('spawn')->with('hello', false)->once();
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
