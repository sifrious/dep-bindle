<?php

declare(strict_types=1);

use Maryeperry\Bindle\Browser\DriverAvailability;
use Maryeperry\Bindle\Browser\DriverKind;
use Maryeperry\Bindle\Support\UrlProbe;

function unreachableProbe(): UrlProbe
{
    return new class extends UrlProbe
    {
        public function unreachableReason(string $url): ?string
        {
            return 'Nothing is listening.';
        }
    };
}

function reachableProbe(): UrlProbe
{
    return new class extends UrlProbe
    {
        public function unreachableReason(string $url): ?string
        {
            return null;
        }
    };
}

it('asks nothing of the placeholder driver', function (): void {
    expect(app(DriverAvailability::class)->requirements(DriverKind::Placeholder))->toBe([])
        ->and(app(DriverAvailability::class)->isAvailable(DriverKind::Placeholder))->toBeTrue();
});

it('reports dusk as unavailable in a bare package checkout', function (): void {
    $availability = new DriverAvailability($this->app, config(), unreachableProbe());

    expect($availability->isAvailable(DriverKind::Dusk))->toBeFalse()
        ->and($availability->unmet(DriverKind::Dusk))->not->toBeEmpty();
});

it('offers only the placeholder driver when dusk cannot run', function (): void {
    $availability = new DriverAvailability($this->app, config(), unreachableProbe());

    expect($availability->availableKinds())->toBe([DriverKind::Placeholder]);
});

it('names a missing server as an unmet requirement', function (): void {
    $availability = new DriverAvailability($this->app, config(), unreachableProbe());

    $keys = array_map(fn ($r): string => $r->key, $availability->unmet(DriverKind::Dusk));

    expect($keys)->toContain('app-serving');
});

it('stops naming the server once something answers', function (): void {
    $availability = new DriverAvailability($this->app, config(), reachableProbe());

    $keys = array_map(fn ($r): string => $r->key, $availability->unmet(DriverKind::Dusk));

    expect($keys)->not->toContain('app-serving');
});

it('carries a fix command on every requirement', function (): void {
    $availability = new DriverAvailability($this->app, config(), unreachableProbe());

    foreach ($availability->requirements(DriverKind::Dusk) as $requirement) {
        expect($requirement->command)->not->toBe('')
            ->and($requirement->consequence)->not->toBe('');
    }
});

it('only offers a panel button for artisan-runnable fixes', function (): void {
    $availability = new DriverAvailability($this->app, config(), unreachableProbe());

    foreach ($availability->requirements(DriverKind::Dusk) as $requirement) {
        if ($requirement->action !== null) {
            expect($availability->commandFor($requirement->action))->not->toBeNull();
        }
    }

    expect($availability->commandFor('composer-install'))->toBeNull();
});
