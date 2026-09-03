<?php

declare(strict_types=1);

use Maryeperry\Bindle\Exceptions\MalformedManifestException;
use Maryeperry\Bindle\Requirements\Domain\ApplicationRequirementsManifest;
use Maryeperry\Bindle\Requirements\Domain\Confidence;
use Maryeperry\Bindle\Requirements\Domain\Evidence;
use Maryeperry\Bindle\Requirements\Domain\EvidenceLocator;
use Maryeperry\Bindle\Requirements\Domain\EvidenceStrength;
use Maryeperry\Bindle\Requirements\Domain\Requirement;
use Maryeperry\Bindle\Requirements\Domain\RequirementKind;
use Maryeperry\Bindle\Requirements\Domain\RequirementNecessity;
use Maryeperry\Bindle\Requirements\Domain\VersionConstraint;

function requirement(string $name, RequirementKind $kind, EvidenceStrength $strength = EvidenceStrength::Manifest): Requirement
{
    return new Requirement(
        name: $name,
        kind: $kind,
        necessity: RequirementNecessity::Required,
        confidence: Confidence::High,
        evidence: [new Evidence($strength, new EvidenceLocator('composer.json'), 'excerpt')],
    );
}

it('stamps every payload with the schema version', function (): void {
    $manifest = new ApplicationRequirementsManifest(
        workspaceId: 'ws_1',
        generatedAt: new DateTimeImmutable('2026-09-02T09:00:00+00:00'),
    );

    expect($manifest->toArray()['schemaVersion'])
        ->toBe(ApplicationRequirementsManifest::SCHEMA_VERSION)
        ->toBe('bindle.application-requirements.v1');
});

it('refuses a payload written against a different schema', function (): void {
    $payload = manifestFixture('node-pnpm')->toArray();
    $payload['schemaVersion'] = 'bindle.application-requirements.v2';

    expect(fn (): ApplicationRequirementsManifest => ApplicationRequirementsManifest::fromArray($payload))
        ->toThrow(MalformedManifestException::class, 'Unsupported manifest schema');
});

it('round-trips through JSON without losing anything', function (string $fixture): void {
    $original = manifestFixture($fixture);

    expect(ApplicationRequirementsManifest::fromJson($original->toJson())->toArray())
        ->toBe($original->toArray());
})->with(['laravel-composer-postgres', 'node-pnpm', 'python-uv']);

it('keeps the workspace reference opaque and carries no persistence detail', function (): void {
    $keys = array_keys(manifestFixture('laravel-composer-postgres')->toArray());

    expect($keys)->toBe(['schemaVersion', 'workspaceId', 'revision', 'generatedAt', 'requirements']);
});

it('finds requirements by name and kind', function (): void {
    $manifest = new ApplicationRequirementsManifest(
        workspaceId: 'ws_1',
        generatedAt: new DateTimeImmutable('2026-09-02T09:00:00+00:00'),
        requirements: [
            requirement('php', RequirementKind::Runtime),
            requirement('node', RequirementKind::Runtime),
            requirement('composer', RequirementKind::PackageManager),
        ],
    );

    expect($manifest->ofKind(RequirementKind::Runtime))->toHaveCount(2)
        ->and($manifest->ofKind(RequirementKind::Service))->toBe([])
        ->and($manifest->findByName('composer')?->kind)->toBe(RequirementKind::PackageManager)
        ->and($manifest->findByName('nothing-here'))->toBeNull();
});

it('preserves a version constraint exactly as the source wrote it', function (): void {
    $constraint = new VersionConstraint('^8.3');

    expect($constraint->raw)->toBe('^8.3')
        ->and($constraint->isNormalized())->toBeFalse()
        ->and((new VersionConstraint('^8.3', '8.3.0'))->isNormalized())->toBeTrue();
});

it('rejects a timestamp that is not ISO-8601', function (): void {
    $payload = manifestFixture('node-pnpm')->toArray();
    $payload['generatedAt'] = 'last Tuesday';

    expect(fn (): ApplicationRequirementsManifest => ApplicationRequirementsManifest::fromArray($payload))
        ->toThrow(MalformedManifestException::class, 'ISO-8601');
});
