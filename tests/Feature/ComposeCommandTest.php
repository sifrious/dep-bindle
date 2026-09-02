<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;

it('emits a deterministic dry-run plan without writing files', function (): void {
    $directory = sys_get_temp_dir().'/bindle-compose-'.bin2hex(random_bytes(4));
    mkdir($directory);
    $storyPath = $directory.'/story.json';
    $compositionPath = $directory.'/composition.json';
    file_put_contents($storyPath, json_encode([
        'schema_version' => '1.0', 'id' => 'change-story', 'title' => 'Change story',
        'source' => ['kind' => 'git-behavior', 'path' => '/inputs/Git Behavior.md'],
        'target' => ['root' => '/apps/burdgeon', 'paths' => ['resources/views/change-story.blade.php']],
        'behaviors' => [[
            'id' => 'GIT-112', 'order' => 1, 'title' => 'Present chapters',
            'given' => ['a validated story'], 'when' => ['the page renders'], 'then' => ['chapters are ordered'],
        ]],
    ], JSON_THROW_ON_ERROR));
    file_put_contents($compositionPath, json_encode([
        'schema_version' => '1.0', 'id' => 'change-story-page', 'story_id' => 'change-story',
        'framework' => 'blade', 'paths' => ['resources/views/change-story.blade.php'],
        'states' => [['id' => 'default', 'label' => 'Default']],
        'regions' => [[
            'id' => 'chapters', 'ref' => ['id' => 'chapter-list', 'status' => 'proposal'],
            'states' => ['default'], 'behaviors' => ['GIT-112'],
        ]],
    ], JSON_THROW_ON_ERROR));

    expect(Artisan::call('bindle:compose', ['story' => $storyPath, 'composition' => $compositionPath]))->toBe(0);
    $first = Artisan::output();
    expect($first)->toContain('"mode": "dry-run"')->toContain('change-story-page')
        ->and(file_exists('/apps/burdgeon/resources/views/change-story.blade.php'))->toBeFalse();
    Artisan::call('bindle:compose', ['story' => $storyPath, 'composition' => $compositionPath]);
    expect(Artisan::output())->toBe($first);
});

it('plans the five-story Burdgeon slice while preserving its existing layout', function (): void {
    $fixtures = dirname(__DIR__).'/Fixtures/Composition';
    $layout = '/Users/mme/gits/sifrious/burdgeon/resources/views/layouts/app.blade.php';
    $before = is_file($layout) ? hash_file('sha256', $layout) : null;

    expect(Artisan::call('bindle:compose', [
        'story' => $fixtures.'/burdgeon-change-story.behavior-story.json',
        'composition' => $fixtures.'/burdgeon-change-story.page-composition.json',
        '--catalog' => $fixtures.'/burdgeon.scan-catalog.json',
    ]))->toBe(0);

    $payload = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);
    expect($payload['mode'])->toBe('dry-run')
        ->and($payload['story_id'])->toBe('burdgeon-change-story')
        ->and($payload['artifacts'])->toHaveCount(3)
        ->and(is_file($layout) ? hash_file('sha256', $layout) : null)->toBe($before);
});
