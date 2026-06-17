<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

beforeEach(function (): void {
    config()->set('bindle.database_path', ':memory:');
    Route::get('/hello', fn () => 'Hello World')->name('hello');
});

it('serves the panel in local with the flag enabled', function (): void {
    $this->get('/_bindle')
        ->assertOk()
        ->assertSee('hello');
});

it('404s when the panel flag is disabled at request time', function (): void {
    config()->set('bindle.panel.enabled', false);

    $this->get('/_bindle')->assertNotFound();
});

it('404s when the environment is no longer local at request time', function (): void {
    app()['env'] = 'production';
    config()->set('app.env', 'production');

    $this->get('/_bindle')->assertNotFound();
});
