<?php

declare(strict_types=1);

use Maryeperry\Bindle\Exceptions\ProductionForbiddenException;

it('refuses to run bindle:scan in production', function (): void {
    config()->set('app.env', 'production');
    config()->set('bindle.enabled', false);

    expect(fn () => $this->artisan('bindle:scan'))
        ->toThrow(ProductionForbiddenException::class);
});

it('refuses to run bindle:reset in production', function (): void {
    config()->set('app.env', 'production');
    config()->set('bindle.enabled', false);

    expect(fn () => $this->artisan('bindle:reset', ['--force' => true]))
        ->toThrow(ProductionForbiddenException::class);
});

it('refuses to run bindle:errors in production', function (): void {
    config()->set('app.env', 'production');
    config()->set('bindle.enabled', false);

    expect(fn () => $this->artisan('bindle:errors'))
        ->toThrow(ProductionForbiddenException::class);
});

it('refuses to run bindle:install in production', function (): void {
    config()->set('app.env', 'production');
    config()->set('bindle.enabled', false);

    expect(fn () => $this->artisan('bindle:install'))
        ->toThrow(ProductionForbiddenException::class);
});
