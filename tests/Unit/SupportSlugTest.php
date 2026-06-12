<?php

declare(strict_types=1);

use Maryeperry\Bindle\Support\Slug;

it('slugifies arbitrary strings', function (): void {
    expect(Slug::of('Hello, World!'))->toBe('hello-world');
    expect(Slug::of('users.show'))->toBe('users-show');
    expect(Slug::of('  --  '))->toBe('untitled');
});

it('builds a stable slug for a named route', function (): void {
    expect(Slug::forRoute('users.show', '/users/{user}', 'GET'))->toBe('users-show');
});

it('falls back to method+uri for unnamed routes', function (): void {
    expect(Slug::forRoute(null, '/dashboard/billing', 'GET'))->toBe('get-dashboard-billing');
});

it('namespaces a component slug by kind', function (): void {
    expect(Slug::forComponent('livewire', 'CounterPanel'))->toBe('livewire-counterpanel');
    expect(Slug::forComponent('vue', 'Components/Button'))->toBe('vue-components-button');
});
