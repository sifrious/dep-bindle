<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Maryeperry\Bindle\Browser\NullBrowserDriver;
use Maryeperry\Bindle\Pipeline\ScanPipeline;
use Maryeperry\Bindle\Storage\Database\DatabaseManager;
use Maryeperry\Bindle\Storage\Models\Page;
use Maryeperry\Bindle\Storage\Models\Run;

beforeEach(function (): void {
    $this->outputPath = sys_get_temp_dir().'/bindle-test-'.uniqid();
    config()->set('bindle.output_path', $this->outputPath);
    config()->set('bindle.database_path', ':memory:');
    config()->set('bindle.routes.exclude', ['horizon*', 'telescope*', '_ignition*', 'sanctum*', 'livewire*', '_dusk*']);

    Route::get('/hello', fn () => 'Hello World')->name('hello');
    Route::get('/form', fn () => 'A form')->name('form');

    app(DatabaseManager::class)->ensureSchema();
});

afterEach(function (): void {
    if (isset($this->outputPath) && is_dir($this->outputPath)) {
        bindleRemoveDir($this->outputPath);
    }
});

it('scans only the named route when a route filter is given', function (): void {
    $run = app(ScanPipeline::class)->run(new NullBrowserDriver, [], false, 'hello');

    $routeNames = Page::query()->where('run_id', $run->id)->pluck('route_name')->all();

    expect($routeNames)->toContain('hello');
    expect($routeNames)->not->toContain('form');
    expect($routeNames)->toHaveCount(1);
});

it('matches the route filter by URI as well as name', function (): void {
    $run = app(ScanPipeline::class)->run(new NullBrowserDriver, [], false, 'form');

    $routeNames = Page::query()->where('run_id', $run->id)->pluck('route_name')->all();

    expect($routeNames)->toBe(['form']);
});

it('exposes the route filter through the bindle:scan --route option', function (): void {
    $this->artisan('bindle:scan', ['--route' => 'hello'])->assertSuccessful();

    $latest = app(Run::class)->newQuery()->latest('id')->firstOrFail();
    $routeNames = Page::query()->where('run_id', $latest->id)->pluck('route_name')->all();

    expect($routeNames)->toBe(['hello']);
});

function bindleRemoveDir(string $path): void
{
    foreach (scandir($path) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $full = $path.'/'.$entry;
        is_dir($full) ? bindleRemoveDir($full) : @unlink($full);
    }
    @rmdir($path);
}
