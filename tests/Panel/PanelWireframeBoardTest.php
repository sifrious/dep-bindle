<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

beforeEach(function (): void {
    config()->set('bindle.database_path', ':memory:');
    Route::get('/hello', fn () => 'Hello World')->name('hello');
});

it('exposes the wireframe board from the inventory screen', function (): void {
    $this->get('/_bindle')
        ->assertOk()
        ->assertSee(route('bindle.panel.wireframe-board'), false);
});

it('renders the wireframe board defaulting to empty state', function (): void {
    $this->get(route('bindle.panel.wireframe-board'))
        ->assertOk()
        ->assertSee('Wireframe board')
        ->assertSee('Board state:')
        ->assertSee('state-empty')
        ->assertSee('Global header')
        ->assertSee('Primary hero')
        ->assertSee('Main content')
        ->assertSee('Supplementary rail')
        ->assertSee('Footer/meta');
});

it('renders each explicit board state', function (string $state): void {
    $this->get(route('bindle.panel.wireframe-board', ['state' => $state]))
        ->assertOk()
        ->assertSee('state-'.$state)
        ->assertSee(ucfirst($state));
})->with([
    'empty',
    'loading',
    'error',
    'populated',
]);

it('falls back to empty when given an unknown state', function (): void {
    $this->get(route('bindle.panel.wireframe-board', ['state' => 'mystery']))
        ->assertOk()
        ->assertSee('Unknown state requested.')
        ->assertSee('state-empty');
});
