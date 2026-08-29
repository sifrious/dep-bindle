<?php

declare(strict_types=1);

use Maryeperry\Bindle\Composition\Contracts\BehaviorStory;
use Maryeperry\Bindle\Composition\Contracts\ContractValidationException;
use Maryeperry\Bindle\Composition\Contracts\PageComposition;

function behaviorStoryDocument(): array
{
    return [
        'schema_version' => '1.0',
        'id' => 'burdgeon.change-story',
        'title' => 'Change story',
        'source' => ['kind' => 'git-behavior', 'path' => '/Users/example/vault/Git Behavior.md'],
        'target' => ['root' => '/Users/example/burdgeon', 'paths' => ['app/Livewire/ChangeStory.php', 'resources/views/change-story.blade.php']],
        'behaviors' => [
            ['id' => 'MME-GB-001', 'order' => 1, 'title' => 'See a change', 'given' => ['a commit exists'], 'when' => ['I visit its page'], 'then' => ['I see its message']],
            ['id' => 'MME-GB-002', 'order' => 2, 'title' => 'See changed files', 'given' => ['a commit has changes'], 'when' => ['I expand files'], 'then' => ['I see the paths']],
        ],
    ];
}

function pageCompositionDocument(): array
{
    return [
        'schema_version' => '1.0',
        'id' => 'burdgeon.change-story.page',
        'story_id' => 'burdgeon.change-story',
        'framework' => 'livewire',
        'paths' => ['app/Livewire/ChangeStory.php', 'resources/views/change-story.blade.php'],
        'states' => [
            ['id' => 'default', 'label' => 'Change summary'],
            ['id' => 'files-expanded', 'label' => 'Changed files expanded'],
        ],
        'regions' => [
            ['id' => 'summary', 'ref' => ['id' => 'layout.app', 'status' => 'reuse'], 'states' => ['default'], 'behaviors' => ['MME-GB-001']],
            ['id' => 'files', 'ref' => ['id' => 'change-files', 'status' => 'proposal'], 'states' => ['files-expanded'], 'behaviors' => ['MME-GB-002']],
        ],
    ];
}

it('accepts and round trips a versioned git behavior story', function (): void {
    $document = behaviorStoryDocument();
    $story = BehaviorStory::fromArray($document);

    expect($story->id)->toBe('burdgeon.change-story')
        ->and($story->behaviorIds())->toBe(['MME-GB-001', 'MME-GB-002'])
        ->and($story->toArray())->toBe($document);
});

it('rejects unsafe and ambiguous behavior story input', function (Closure $mutate, string $message): void {
    $document = behaviorStoryDocument();
    $mutate($document);

    expect(fn (): BehaviorStory => BehaviorStory::fromArray($document))
        ->toThrow(ContractValidationException::class, $message);
})->with([
    'unsupported version' => [fn (array &$document): string => $document['schema_version'] = '2.0', 'schema_version'],
    'ambiguous id' => [fn (array &$document): string => $document['id'] = 'change story', 'unambiguous'],
    'source traversal' => [fn (array &$document): string => $document['source']['path'] = '/Users/example/../secret', 'source'],
    'target traversal' => [fn (array &$document): string => $document['target']['paths'][0] = '../outside.php', 'traversal-safe'],
    'duplicate paths' => [fn (array &$document) => $document['target']['paths'][1] = $document['target']['paths'][0], 'unique'],
    'out of order behavior' => [fn (array &$document): int => $document['behaviors'][1]['order'] = 3, 'order must be 2'],
    'duplicate behavior' => [fn (array &$document): string => $document['behaviors'][1]['id'] = 'MME-GB-001', 'ids must be unique'],
    'missing outcome' => [fn (array &$document): array => $document['behaviors'][0]['then'] = [], 'then must be a non-empty'],
]);

it('accepts a provider-independent composition with complete behavior mappings', function (): void {
    $document = pageCompositionDocument();

    expect(PageComposition::fromArray($document, BehaviorStory::fromArray(behaviorStoryDocument()))->toArray())
        ->toBe($document);
});

it('rejects invalid composition regions refs states paths frameworks and mappings', function (Closure $mutate, string $message): void {
    $document = pageCompositionDocument();
    $mutate($document);

    expect(fn (): PageComposition => PageComposition::fromArray($document, BehaviorStory::fromArray(behaviorStoryDocument())))
        ->toThrow(ContractValidationException::class, $message);
})->with([
    'unsupported framework' => [fn (array &$document): string => $document['framework'] = 'react', 'blade or livewire'],
    'unsafe path' => [fn (array &$document): string => $document['paths'][0] = '/tmp/owned.php', 'traversal-safe'],
    'duplicate state' => [fn (array &$document): string => $document['states'][1]['id'] = 'default', 'states ids must be unique'],
    'unknown state ref' => [fn (array &$document): array => $document['regions'][0]['states'] = ['missing'], 'declared states'],
    'invalid reuse ref' => [fn (array &$document): string => $document['regions'][0]['ref']['id'] = '../layout', 'valid id'],
    'ambiguous ref status' => [fn (array &$document): string => $document['regions'][0]['ref']['status'] = 'maybe', 'reuse or proposal'],
    'duplicate region' => [fn (array &$document): string => $document['regions'][1]['id'] = 'summary', 'regions ids must be unique'],
    'duplicate mapping' => [fn (array &$document): array => $document['regions'][1]['behaviors'] = ['MME-GB-001'], 'only one region'],
    'unknown mapping' => [fn (array &$document): array => $document['regions'][1]['behaviors'] = ['MME-GB-999'], 'unknown behaviors'],
    'missing mapping' => [fn (array &$document) => array_pop($document['regions']), 'does not map behaviors'],
]);
