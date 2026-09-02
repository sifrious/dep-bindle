<?php

declare(strict_types=1);

use Maryeperry\Bindle\Exceptions\MalformedManifestException;
use Maryeperry\Bindle\Requirements\Domain\Confidence;
use Maryeperry\Bindle\Requirements\Domain\ConflictKind;
use Maryeperry\Bindle\Requirements\Domain\Evidence;
use Maryeperry\Bindle\Requirements\Domain\EvidenceLocator;
use Maryeperry\Bindle\Requirements\Domain\EvidenceStrength;
use Maryeperry\Bindle\Requirements\Domain\Requirement;
use Maryeperry\Bindle\Requirements\Domain\RequirementConflict;
use Maryeperry\Bindle\Requirements\Domain\RequirementKind;
use Maryeperry\Bindle\Requirements\Domain\RequirementNecessity;
use Maryeperry\Bindle\Requirements\Domain\SetupHint;
use Maryeperry\Bindle\Requirements\Domain\SetupIntent;

function evidenceFrom(EvidenceStrength $strength, string $path, string $excerpt = 'x'): Evidence
{
    return new Evidence($strength, new EvidenceLocator($path), $excerpt);
}

function requirementWith(Evidence ...$evidence): Requirement
{
    return new Requirement(
        name: 'php',
        kind: RequirementKind::Runtime,
        necessity: RequirementNecessity::Required,
        confidence: Confidence::High,
        evidence: array_values($evidence),
    );
}

it('refuses a requirement that cannot be explained', function (): void {
    expect(fn (): Requirement => new Requirement(
        name: 'php',
        kind: RequirementKind::Runtime,
        necessity: RequirementNecessity::Required,
        confidence: Confidence::High,
        evidence: [],
    ))->toThrow(MalformedManifestException::class, 'at least one evidence record');
});

it('ranks a lockfile above a manifest and prose below everything', function (): void {
    expect(EvidenceStrength::Lockfile->outranks(EvidenceStrength::Manifest))->toBeTrue()
        ->and(EvidenceStrength::Manifest->outranks(EvidenceStrength::VersionFile))->toBeTrue()
        ->and(EvidenceStrength::VersionFile->outranks(EvidenceStrength::Config))->toBeTrue()
        ->and(EvidenceStrength::Config->outranks(EvidenceStrength::Automation))->toBeTrue()
        ->and(EvidenceStrength::Automation->outranks(EvidenceStrength::Documentation))->toBeTrue()
        ->and(EvidenceStrength::Documentation->outranks(EvidenceStrength::Lockfile))->toBeFalse();
});

it('treats documentation as evidence but never as authority', function (): void {
    expect(EvidenceStrength::Documentation->isAuthoritative())->toBeFalse()
        ->and(EvidenceStrength::Lockfile->isAuthoritative())->toBeTrue()
        ->and(EvidenceStrength::Automation->isAuthoritative())->toBeTrue();
});

it('picks the strongest source behind a requirement', function (): void {
    $requirement = requirementWith(
        evidenceFrom(EvidenceStrength::Documentation, 'README.md'),
        evidenceFrom(EvidenceStrength::Lockfile, 'composer.lock'),
        evidenceFrom(EvidenceStrength::Manifest, 'composer.json'),
    );

    expect($requirement->strongestEvidence()->strength)->toBe(EvidenceStrength::Lockfile);
});

it('flags a requirement that rests on prose alone', function (): void {
    expect(requirementWith(evidenceFrom(EvidenceStrength::Documentation, 'README.md'))->restsOnlyOnDocumentation())
        ->toBeTrue()
        ->and(requirementWith(
            evidenceFrom(EvidenceStrength::Documentation, 'README.md'),
            evidenceFrom(EvidenceStrength::Config, '.env.example'),
        )->restsOnlyOnDocumentation())->toBeFalse();
});

it('keeps both sides of a disagreement instead of resolving it', function (): void {
    $conflict = new RequirementConflict(
        kind: ConflictKind::VersionDisagreement,
        evidence: [
            evidenceFrom(EvidenceStrength::Manifest, 'composer.json', '^8.3'),
            evidenceFrom(EvidenceStrength::VersionFile, '.tool-versions', 'php 8.2.10'),
        ],
        note: 'composer.json requires ^8.3; .tool-versions pins 8.2.10.',
    );

    expect($conflict->evidence)->toHaveCount(2)
        ->and($conflict->strongest()?->excerpt)->toBe('^8.3')
        ->and(requirementWith(evidenceFrom(EvidenceStrength::Manifest, 'composer.json'))->isContested())->toBeFalse();
});

it('reports no winner when two sources of equal strength disagree', function (): void {
    $conflict = new RequirementConflict(
        kind: ConflictKind::VersionDisagreement,
        evidence: [
            evidenceFrom(EvidenceStrength::Manifest, 'composer.json', '^8.3'),
            evidenceFrom(EvidenceStrength::Manifest, 'packages/api/composer.json', '^8.2'),
        ],
    );

    expect($conflict->strongest())->toBeNull();
});

it('describes a locator by pointer, line, or path in that order', function (): void {
    expect((new EvidenceLocator('composer.json', null, '/require/php'))->describe())->toBe('composer.json/require/php')
        ->and((new EvidenceLocator('README.md', 42))->describe())->toBe('README.md:42')
        ->and((new EvidenceLocator('uv.lock'))->describe())->toBe('uv.lock');
});

it('states setup intent without ever carrying a command', function (): void {
    $hint = new SetupHint(SetupIntent::EnsureService, 'postgresql');

    $fields = array_map(
        static fn (ReflectionProperty $property): string => strtolower($property->getName()),
        (new ReflectionClass(SetupHint::class))->getProperties(),
    );

    expect($hint->describe())->toBe('ensure_service postgresql')
        ->and($fields)->not->toContain('command')
        ->and($fields)->not->toContain('script')
        ->and($fields)->not->toContain('shell')
        ->and(SetupIntent::RequireUserAuthentication->requiresHuman())->toBeTrue()
        ->and(SetupIntent::EnsureService->requiresHuman())->toBeFalse();
});

it('separates optional requirements from ones whose absence is a gap', function (): void {
    expect(RequirementNecessity::Optional->absenceIsAGap())->toBeFalse()
        ->and(RequirementNecessity::Required->absenceIsAGap())->toBeTrue()
        ->and(RequirementNecessity::DevOnly->absenceIsAGap())->toBeTrue()
        ->and(RequirementNecessity::TestOnly->absenceIsAGap())->toBeTrue();
});

it('knows which kinds can carry a version', function (): void {
    expect(RequirementKind::Runtime->carriesVersion())->toBeTrue()
        ->and(RequirementKind::Service->carriesVersion())->toBeTrue()
        ->and(RequirementKind::EnvironmentVariable->carriesVersion())->toBeFalse()
        ->and(RequirementKind::BuildStep->carriesVersion())->toBeFalse();
});

it('rejects an unknown enum value from the wire', function (): void {
    expect(fn (): Evidence => Evidence::fromArray([
        'strength' => 'hearsay',
        'locator' => ['relativePath' => 'README.md'],
        'excerpt' => 'x',
    ]))->toThrow(MalformedManifestException::class, 'unknown evidence strength value "hearsay"');
});
