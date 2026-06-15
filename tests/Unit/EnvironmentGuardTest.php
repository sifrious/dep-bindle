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

it('refuses to run when a bare (undelimited) host pattern matches app.url', function (): void {
    config()->set('app.env', 'local');
    config()->set('bindle.enabled', true);
    // No regex delimiters — the natural value a user would set.
    config()->set('bindle.production_host_pattern', 'pinkary\\.com');
    config()->set('app.url', 'https://pinkary.com');

    $env = new Environment(app(), app('config'));

    $env->assertSafe();
})->throws(ProductionForbiddenException::class, 'pinkary.com');

it('permits a local host even when a production host pattern is set', function (): void {
    config()->set('app.env', 'local');
    config()->set('bindle.enabled', true);
    config()->set('bindle.production_host_pattern', 'pinkary\\.com');
    config()->set('app.url', 'http://127.0.0.1:8000');

    $env = new Environment(app(), app('config'));

    expect(fn () => $env->assertSafe())->not->toThrow(ProductionForbiddenException::class);
});

it('permits a safe local environment', function (): void {
    config()->set('app.env', 'local');
    config()->set('bindle.enabled', true);
    config()->set('bindle.production_host_pattern', '');

    $env = new Environment(app(), app('config'));

    expect(fn () => $env->assertSafe())->not->toThrow(ProductionForbiddenException::class);
});
