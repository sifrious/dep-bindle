<?php

declare(strict_types=1);

use Maryeperry\Bindle\Browser\DriverKind;
use Maryeperry\Bindle\Browser\NullBrowserDriver;

it('defaults anything it does not recognise to the placeholder driver', function (string $option): void {
    expect(DriverKind::fromOption($option))->toBe(DriverKind::Placeholder);
})->with(['null', '', 'nonsense', 'NULL', ' placeholder ']);

it('recognises dusk case-insensitively', function (string $option): void {
    expect(DriverKind::fromOption($option))->toBe(DriverKind::Dusk);
})->with(['dusk', 'DUSK', ' Dusk ']);

it('only claims real screenshots for dusk', function (): void {
    expect(DriverKind::Dusk->producesRealScreenshots())->toBeTrue()
        ->and(DriverKind::Placeholder->producesRealScreenshots())->toBeFalse();
});

it('makes the placeholder driver announce what it cannot do', function (): void {
    expect(DriverKind::Placeholder->label())->toBe('no screenshots')
        ->and(DriverKind::Placeholder->describe())->toContain('1x1 placeholder')
        ->and(DriverKind::Placeholder->describe())->toContain('Alpine');
});

it('lets a driver declare its own kind', function (): void {
    expect((new NullBrowserDriver)->kind())->toBe(DriverKind::Placeholder);
});
