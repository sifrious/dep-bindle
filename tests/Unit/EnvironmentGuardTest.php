<?php

declare(strict_types=1);

use Maryeperry\Bindle\Exceptions\ProductionForbiddenException;
use Maryeperry\Bindle\Support\Environment;

it('refuses to run in production', function (): void {
    config()->set('app.env', 'production');
    config()->set('bindle.enabled', true);

    $env = new Environment(app(), app('config'));

    $env->assertSafe();
})->throws(ProductionForbiddenException::class, 'production');

it('refuses to run when bindle.enabled is false', function (): void {
    config()->set('app.env', 'local');
    config()->set('bindle.enabled', false);

    $env = new Environment(app(), app('config'));

    $env->assertSafe();
})->throws(ProductionForbiddenException::class, 'disabled');

it('refuses to run when app.url matches the configured production host pattern', function (): void {
    config()->set('app.env', 'local');
    config()->set('bindle.enabled', true);
    config()->set('bindle.production_host_pattern', '/example\\.com$/i');
    config()->set('app.url', 'https://example.com');

    $env = new Environment(app(), app('config'));

    $env->assertSafe();
})->throws(ProductionForbiddenException::class, 'example.com');

it('permits a safe local environment', function (): void {
    config()->set('app.env', 'local');
    config()->set('bindle.enabled', true);
    config()->set('bindle.production_host_pattern', '');

    $env = new Environment(app(), app('config'));

    expect(fn () => $env->assertSafe())->not->toThrow(ProductionForbiddenException::class);
});
