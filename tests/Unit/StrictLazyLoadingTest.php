<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Maryeperry\Bindle\Storage\Database\DatabaseManager;
use Maryeperry\Bindle\Storage\Models\Component;
use Maryeperry\Bindle\Storage\Models\Run;

beforeEach(function (): void {
    config()->set('bindle.database_path', ':memory:');
    app(DatabaseManager::class)->ensureSchema();
});

afterEach(function (): void {
    Model::preventLazyLoading(false);
});

it('lets Bindle models lazy-load even when the host app forbids it', function (): void {
    // Mirror a strict-mode host app (e.g. pinkary).
    Model::preventLazyLoading(true);

    $run = Run::create([
        'environment' => 'testing',
        'status' => 'running',
        'bindle_version' => '0.1.0-dev',
    ]);

    $component = Component::create([
        'run_id' => $run->id,
        'name' => 'app-layout',
        'slug' => 'blade-anon-app-layout',
        'kind' => 'blade-anon',
    ]);

    // Re-fetch so relations are unquestionably unloaded, then access lazily.
    $fresh = Component::query()->findOrFail($component->id);

    // Without the BindleModel override these would throw LazyLoadingViolationException.
    expect($fresh->props)->toHaveCount(0);
    expect($fresh->variants)->toHaveCount(0);
});
