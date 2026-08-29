<?php

declare(strict_types=1);

use Maryeperry\Bindle\Composition\Artifacts\AcceptanceTestGenerator;
use Maryeperry\Bindle\Composition\Artifacts\GeneratedArtifact;
use Maryeperry\Bindle\Composition\Artifacts\SafeScaffoldWriter;
use Maryeperry\Bindle\Composition\Artifacts\ScaffoldPlan;
use Maryeperry\Bindle\Composition\Artifacts\WireframeGenerator;
use Maryeperry\Bindle\Composition\Contracts\PageComposition;

function artifactDirectory(): string
{
    $directory = sys_get_temp_dir().'/bindle-artifacts-'.bin2hex(random_bytes(6));
    mkdir($directory, 0o755, true);

    return $directory;
}

it('generates deterministic labeled desktop and mobile wireframes', function (): void {
    $composition = [
        'id' => 'change-story',
        'regions' => [[
            'id' => 'change-list',
            'label' => 'Changes',
            'component_ref' => 'components.change-list',
            'status' => 'reuse',
            'behavior_ids' => ['GIT-2', 'GIT-1'],
            'states' => ['empty', 'populated'],
            'unresolved_decisions' => ['Pagination threshold'],
        ], [
            'id' => 'change-detail',
            'status' => 'proposal',
            'behavior_ids' => ['GIT-3'],
        ]],
    ];

    $artifacts = (new WireframeGenerator)->generate($composition);

    expect($artifacts)->toHaveCount(2)
        ->and(array_column($artifacts, 'path'))->toBe([
            'wireframes/change-story-desktop.html',
            'wireframes/change-story-mobile.html',
        ])
        ->and($artifacts[0]->contents)->toContain('data-viewport="desktop"')
        ->toContain('data-width="1440"')
        ->toContain('GIT-1, GIT-2')
        ->toContain('empty, populated')
        ->toContain('reuse — components.change-list')
        ->toContain('Pagination threshold')
        ->toContain('data-status="proposal"')
        ->toContain('not render evidence')
        ->and($artifacts[1]->contents)->toContain('data-viewport="mobile"')
        ->toContain('data-width="390"')
        ->and((new WireframeGenerator)->generate($composition))->toEqual($artifacts);
});

it('escapes labels in wireframes', function (): void {
    $artifact = (new WireframeGenerator)->generate([
        'id' => 'safe',
        'regions' => [['id' => 'main', 'label' => '<script>alert(1)</script>']],
    ])[0];

    expect($artifact->contents)->not->toContain('<script>')->toContain('&lt;script&gt;');
});

it('consumes the versioned page composition contract', function (): void {
    $composition = PageComposition::fromArray([
        'schema_version' => '1.0',
        'id' => 'change-story',
        'story_id' => 'git-change-story',
        'framework' => 'blade',
        'paths' => ['resources/views/changes/index.blade.php'],
        'states' => [['id' => 'populated', 'label' => 'With changes']],
        'regions' => [[
            'id' => 'change-list',
            'ref' => ['id' => 'existing-change-list', 'status' => 'reuse'],
            'states' => ['populated'],
            'behaviors' => ['GIT-1'],
        ]],
    ]);

    $wireframe = (new WireframeGenerator)->generate($composition)[0];
    $test = (new AcceptanceTestGenerator)->generate($composition)[0];

    expect($wireframe->contents)->toContain('reuse — existing-change-list')
        ->toContain('populated (With changes)')
        ->toContain('GIT-1')
        ->and($test->contents)->toContain("it('GIT-1: GIT-1'")
        ->toContain('->todo()');
});

it('returns a deterministic dry-run patch without writing files', function (): void {
    $root = artifactDirectory();
    $artifact = new GeneratedArtifact('resources/views/changes/index.blade.php', '<main>Changes</main>');
    $writer = new SafeScaffoldWriter($root);

    $plan = $writer->write([$artifact]);

    expect($plan->dryRun)->toBeTrue()
        ->and($plan->manifestPath)->toBeNull()
        ->and($plan->patch)->toContain('--- /dev/null')
        ->toContain('+++ b/resources/views/changes/index.blade.php')
        ->toContain('+<main>Changes</main>')
        ->and(file_exists($root.'/resources/views/changes/index.blade.php'))->toBeFalse();
});

it('writes create-only allowlisted scaffolds and a recoverable manifest', function (): void {
    $root = artifactDirectory();
    $artifacts = [
        new GeneratedArtifact('resources/views/changes/index.blade.php', '<main>Changes</main>'),
        new GeneratedArtifact('tests/Feature/ChangeStoryTest.php', '<?php'),
    ];
    $writer = new SafeScaffoldWriter($root);

    $plan = $writer->write($artifacts, false);

    expect($plan->dryRun)->toBeFalse()
        ->and(file_get_contents($root.'/resources/views/changes/index.blade.php'))->toBe('<main>Changes</main>')
        ->and($plan->manifestPath)->not->toBeNull()
        ->and(json_decode((string) file_get_contents((string) $plan->manifestPath), true, flags: JSON_THROW_ON_ERROR))->toBe([
            'version' => 1,
            'created' => ['resources/views/changes/index.blade.php', 'tests/Feature/ChangeStoryTest.php'],
        ]);

    expect(fn (): ScaffoldPlan => $writer->write([$artifacts[0]], false))
        ->toThrow(RuntimeException::class, 'Refusing to overwrite');
});

it('rejects traversal non-allowlisted and symlink scaffold paths', function (): void {
    $root = artifactDirectory();
    $writer = new SafeScaffoldWriter($root);

    expect(fn (): ScaffoldPlan => $writer->write([new GeneratedArtifact('resources/views/../../.env', 'bad')]))
        ->toThrow(InvalidArgumentException::class, 'Unsafe scaffold path')
        ->and(fn (): ScaffoldPlan => $writer->write([new GeneratedArtifact('config/bindle.php', 'bad')]))
        ->toThrow(InvalidArgumentException::class, 'not allowlisted');

    mkdir($root.'/outside', 0o755, true);
    mkdir($root.'/resources', 0o755, true);
    symlink($root.'/outside', $root.'/resources/views');

    expect(fn (): ScaffoldPlan => $writer->write([new GeneratedArtifact('resources/views/escape.blade.php', 'bad')]))
        ->toThrow(RuntimeException::class, 'symbolic link');
});

it('generates one acceptance skeleton for every selected behavior', function (): void {
    $artifact = (new AcceptanceTestGenerator)->generate([
        'id' => 'change-story',
        'selected_behaviors' => [
            ['id' => 'GIT-3', 'title' => 'Show changed paths'],
            ['id' => 'GIT-1', 'title' => 'List changes'],
            'GIT-2',
        ],
    ])[0];

    expect($artifact->path)->toBe('tests/Feature/ChangeStoryCompositionTest.php')
        ->and(substr_count($artifact->contents, "it('GIT-"))->toBe(3)
        ->and($artifact->contents)->toContain("it('GIT-1: List changes'")
        ->toContain("it('GIT-2: GIT-2'")
        ->toContain("it('GIT-3: Show changed paths'")
        ->toContain("->group('bindle-generated', 'GIT-1')");
});
