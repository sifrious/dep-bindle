<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Maryeperry\Bindle\Browser\DriverKind;
use Maryeperry\Bindle\Browser\NullBrowserDriver;
use Maryeperry\Bindle\Pipeline\ScanPipeline;
use Maryeperry\Bindle\Storage\Database\DatabaseManager;
use Maryeperry\Bindle\Storage\Models\ErrorLog;
use Maryeperry\Bindle\Storage\Models\Page;
use Maryeperry\Bindle\Storage\Models\Run;

beforeEach(function (): void {
    $this->outputPath = sys_get_temp_dir().'/bindle-test-'.uniqid();
    config()->set('bindle.output_path', $this->outputPath);
    config()->set('bindle.database_path', ':memory:');
    config()->set('bindle.routes.exclude', ['horizon*', 'telescope*', '_ignition*', 'sanctum*', 'livewire*', '_dusk*']);

    Route::get('/hello', fn () => 'Hello World')->name('hello');
    Route::get('/form', fn () => '<form><input/><input/><input/></form>')->name('form');

    app(DatabaseManager::class)->ensureSchema();
});

afterEach(function (): void {
    if (isset($this->outputPath) && is_dir($this->outputPath)) {
        bindleCleanupDir($this->outputPath);
    }
});

it('completes an end-to-end scan and writes Markdown', function (): void {
    /** @var ScanPipeline $pipeline */
    $pipeline = app(ScanPipeline::class);
    $driver = (new NullBrowserDriver)->withHtml('<html><body><form><input/><input/><input/></form></body></html>');

    $run = $pipeline->run($driver);

    expect($run)->toBeInstanceOf(Run::class);
    expect($run->status)->toBe('completed');

    $pages = Page::query()->where('run_id', $run->id)->get();
    expect($pages->pluck('route_name')->all())->toContain('hello', 'form');

    expect(is_dir($this->outputPath.'/pages/hello'))->toBeTrue();
    expect(is_file($this->outputPath.'/pages/hello/hello-audit.md'))->toBeTrue();
    expect(is_file($this->outputPath.'/pages/hello/hello-description.md'))->toBeTrue();
    expect(is_file($this->outputPath.'/bindle.md'))->toBeTrue();

    $description = (string) file_get_contents($this->outputPath.'/pages/hello/hello-description.md');
    expect($description)->not->toContain('{');
});

dataset('phases', [['routes'], ['screenshots'], ['components'], ['markdown']]);

it('respects --only phase filtering', function (string $phase): void {
    /** @var ScanPipeline $pipeline */
    $pipeline = app(ScanPipeline::class);
    $driver = new NullBrowserDriver;

    $run = $pipeline->run($driver, [$phase]);
    expect($run->status)->toBe('completed');
})->with('phases');

// Helper used in afterEach
function bindleCleanupDir(string $path): void
{
    foreach (scandir($path) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $full = $path.'/'.$entry;
        is_dir($full) ? bindleCleanupDir($full) : @unlink($full);
    }
    @rmdir($path);
}

it('records which driver produced a run', function (): void {
    $run = app(ScanPipeline::class)->run(new NullBrowserDriver);

    expect($run->driver)->toBe('null')
        ->and($run->driverKind())->toBe(DriverKind::Placeholder);
});

it('warns in the errors table that a placeholder run has no real screenshots', function (): void {
    $run = app(ScanPipeline::class)->run(new NullBrowserDriver);

    $warning = ErrorLog::query()->where('run_id', $run->id)->where('severity', 'warn')->get()
        ->first(fn (ErrorLog $e): bool => str_contains($e->message, 'placeholder driver'));

    expect($warning)->not->toBeNull()
        ->and($warning->message)->toContain('Alpine');
});

it('adds the driver column to a database created before the column existed', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'bindle-legacy-').'.sqlite';
    config()->set('bindle.database_path', $path);

    $db = app(DatabaseManager::class);
    $db->ensureSchema();
    $db->connection()->statement('ALTER TABLE runs DROP COLUMN driver');

    expect($db->connection()->getSchemaBuilder()->hasColumn('runs', 'driver'))->toBeFalse();

    $db->ensureSchema();

    expect($db->connection()->getSchemaBuilder()->hasColumn('runs', 'driver'))->toBeTrue();

    @unlink($path);
});
