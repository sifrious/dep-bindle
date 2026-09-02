<?php

declare(strict_types=1);

use Maryeperry\Bindle\Composition\Catalog\CatalogSnapshot;
use Maryeperry\Bindle\Composition\Catalog\InMemoryScanCatalog;
use Maryeperry\Bindle\Composition\Contracts\BehaviorStory;
use Maryeperry\Bindle\Composition\Contracts\PageComposition;
use Maryeperry\Bindle\Composition\Planning\ReuseFirstRealizationPlanner;
use Maryeperry\Bindle\Composition\Providers\InvalidProviderComposition;
use Maryeperry\Bindle\Composition\Providers\WardrobeCompositionAdapter;

function compositionCatalog(): CatalogSnapshot
{
    return new CatalogSnapshot(
        layouts: [['ref' => 'layouts.app', 'slots' => ['main'], 'source_path' => 'resources/views/layouts/app.blade.php']],
        components: [[
            'ref' => 'components.change-card',
            'kind' => 'blade',
            'props' => [['name' => 'change', 'required' => true]],
            'slots' => ['actions'],
            'dependencies' => ['components.badge'],
            'realizations' => [['route' => 'changes.show']],
            'source_path' => 'resources/views/components/change-card.blade.php',
        ]],
        routes: [['uri' => '/changes/{change}', 'name' => 'changes.show', 'framework' => 'blade']],
        styling: ['colors' => ['accent' => '#663399'], 'font_families' => ['Inter']],
    );
}

function compositionStory(): BehaviorStory
{
    return BehaviorStory::fromArray([
        'schema_version' => '1.0', 'id' => 'change-story', 'title' => 'View a change',
        'source' => ['kind' => 'git-behavior', 'path' => '/tmp/Git Behavior.md'],
        'target' => ['root' => '/tmp/burdgeon', 'paths' => ['resources/views/changes/show.blade.php']],
        'behaviors' => [
            ['id' => 'git.change.view', 'order' => 1, 'title' => 'View', 'given' => ['a change'], 'when' => ['I visit it'], 'then' => ['I see it']],
            ['id' => 'git.change.apply', 'order' => 2, 'title' => 'Apply', 'given' => ['a change'], 'when' => ['I apply it'], 'then' => ['it applies']],
        ],
    ]);
}

/** @param list<array<string, mixed>> $regions */
function pageComposition(array $regions, string $framework = 'livewire'): PageComposition
{
    return PageComposition::fromArray([
        'schema_version' => '1.0', 'id' => 'change-page', 'story_id' => 'change-story',
        'framework' => $framework, 'paths' => ['resources/views/changes/show.blade.php'],
        'states' => [['id' => 'ready', 'label' => 'Ready']], 'regions' => $regions,
    ], compositionStory());
}

it('publishes the complete deterministic scan catalog snapshot', function (): void {
    $catalog = new InMemoryScanCatalog(compositionCatalog());
    $snapshot = $catalog->snapshot()->toArray();

    expect($snapshot['layouts'][0]['slots'])->toBe(['main'])
        ->and($snapshot['components'][0])->toHaveKeys(['props', 'slots', 'dependencies', 'realizations'])
        ->and($snapshot['routes'][0]['name'])->toBe('changes.show')
        ->and($snapshot['styling']['colors']['accent'])->toBe('#663399');
});

it('plans deterministic reuse before proposals and uses livewire only for interaction', function (): void {
    $composition = pageComposition([
        ['id' => 'summary', 'ref' => ['id' => 'components.change-card', 'status' => 'reuse'], 'states' => ['ready'], 'behaviors' => ['git.change.view']],
        ['id' => 'actions', 'ref' => ['id' => 'components.change-actions', 'status' => 'proposal'], 'states' => ['ready'], 'behaviors' => ['git.change.apply']],
    ]);

    $plan = (new ReuseFirstRealizationPlanner)->plan($composition, compositionCatalog())->toArray();

    expect($plan['regions'][0])->toMatchArray(['id' => 'actions', 'status' => 'proposal', 'framework' => 'livewire'])
        ->and($plan['regions'][1])->toMatchArray(['id' => 'summary', 'status' => 'reuse', 'framework' => 'blade'])
        ->and($plan['regions'][1]['source_path'])->toEndWith('change-card.blade.php');
});

it('adapts an optional provider without importing it and rejects invented references', function (): void {
    $valid = new WardrobeCompositionAdapter(fn (array $story, array $catalog): array => [
        'schema_version' => '1.0', 'id' => 'change-page', 'story_id' => $story['id'], 'framework' => 'blade',
        'paths' => ['resources/views/changes/show.blade.php'], 'states' => [['id' => 'ready', 'label' => 'Ready']],
        'regions' => [
            ['id' => 'main', 'ref' => ['id' => $catalog['components'][0]['ref'], 'status' => 'reuse'], 'states' => ['ready'], 'behaviors' => ['git.change.view', 'git.change.apply']],
        ],
    ]);

    expect($valid->compose(compositionStory(), compositionCatalog())->toArray()['story_id'])->toBe('change-story');

    $invalid = new WardrobeCompositionAdapter(fn (): array => [
        'schema_version' => '1.0', 'id' => 'change-page', 'story_id' => 'change-story', 'framework' => 'blade',
        'paths' => ['resources/views/changes/show.blade.php'], 'states' => [['id' => 'ready', 'label' => 'Ready']],
        'regions' => [
            ['id' => 'main', 'ref' => ['id' => 'components.invented', 'status' => 'reuse'], 'states' => ['ready'], 'behaviors' => ['git.change.view', 'git.change.apply']],
        ],
    ]);

    expect(fn (): PageComposition => $invalid->compose(compositionStory(), compositionCatalog()))->toThrow(InvalidProviderComposition::class);
});
