<?php

declare(strict_types=1);

use Maryeperry\Bindle\Requirements\Domain\ConflictKind;
use Maryeperry\Bindle\Requirements\Domain\EvidenceStrength;
use Maryeperry\Bindle\Requirements\Domain\RequirementKind;
use Maryeperry\Bindle\Requirements\Domain\RequirementNecessity;
use Maryeperry\Bindle\Requirements\Domain\SetupIntent;

it('expresses a PHP, Composer and PostgreSQL application', function (): void {
    $manifest = manifestFixture('laravel-composer-postgres');

    expect($manifest->findByName('php')?->kind)->toBe(RequirementKind::Runtime)
        ->and($manifest->findByName('php')?->version?->raw)->toBe('^8.3')
        ->and($manifest->findByName('composer')?->kind)->toBe(RequirementKind::PackageManager)
        ->and($manifest->findByName('postgresql')?->kind)->toBe(RequirementKind::Service)
        ->and($manifest->findByName('DB_DATABASE')?->kind)->toBe(RequirementKind::EnvironmentVariable)
        ->and($manifest->findByName('project-dependencies')?->kind)->toBe(RequirementKind::BuildStep);
});

it('expresses a Node and pnpm application', function (): void {
    $manifest = manifestFixture('node-pnpm');

    expect($manifest->findByName('node')?->version?->raw)->toBe('>=20')
        ->and($manifest->findByName('pnpm')?->strongestEvidence()->strength)->toBe(EvidenceStrength::Lockfile)
        ->and($manifest->ofKind(RequirementKind::PackageManager))->toHaveCount(1);
});

it('expresses a Python and uv application', function (): void {
    $manifest = manifestFixture('python-uv');

    expect($manifest->findByName('python')?->version?->raw)->toBe('>=3.12')
        ->and($manifest->findByName('uv')?->strongestEvidence()->locator->relativePath)->toBe('uv.lock')
        ->and($manifest->findByName('ruff')?->necessity)->toBe(RequirementNecessity::DevOnly);
});

it('keeps the composer-versus-tool-versions disagreement visible', function (): void {
    $php = manifestFixture('laravel-composer-postgres')->findByName('php');

    expect($php?->isContested())->toBeTrue()
        ->and($php?->conflicts[0]->kind)->toBe(ConflictKind::VersionDisagreement)
        ->and($php?->conflicts[0]->evidence)->toHaveCount(2)
        ->and($php?->conflicts[0]->strongest()?->excerpt)->toBe('^8.3')
        ->and($php?->version?->raw)->toBe('^8.3');
});

it('records a README install line as prose, not as an action', function (): void {
    $postgres = manifestFixture('laravel-composer-postgres')->findByName('postgresql');

    $prose = array_values(array_filter(
        $postgres?->evidence ?? [],
        static fn ($evidence): bool => $evidence->strength === EvidenceStrength::Documentation,
    ));

    expect($prose)->toHaveCount(1)
        ->and($prose[0]->excerpt)->toBe('brew install postgresql@16')
        ->and($prose[0]->strength->isAuthoritative())->toBeFalse()
        ->and($postgres?->setupHints[0]->intent)->toBe(SetupIntent::EnsureService)
        ->and($postgres?->setupHints[0]->subject)->toBe('postgresql');
});

it('lists the contested requirements a reconciliation step must treat carefully', function (): void {
    expect(manifestFixture('laravel-composer-postgres')->contested())->toHaveCount(1)
        ->and(manifestFixture('node-pnpm')->contested())->toBe([]);
});
