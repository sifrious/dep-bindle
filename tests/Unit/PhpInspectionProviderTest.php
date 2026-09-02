<?php

declare(strict_types=1);

use Maryeperry\Bindle\Inspection\Domain\InspectionRequest;
use Maryeperry\Bindle\Inspection\Domain\InspectionState;
use Maryeperry\Bindle\Inspection\InspectionService;
use Maryeperry\Bindle\Inspection\PhpInspectionProvider;

it('preserves workspace and relative path provenance for file symbols', function (): void {
    $root = sys_get_temp_dir().'/bindle-inspection-'.bin2hex(random_bytes(4));
    mkdir($root.'/app/Http/Controllers', 0777, true);
    file_put_contents($root.'/app/Http/Controllers/HomeController.php', <<<'PHP'
<?php
class HomeController extends Controller implements Responsable
{
    public function index(): void {}
}
PHP);

    $snapshot = (new PhpInspectionProvider)->inspect(new InspectionRequest('ws_123', $root, 'app/Http/Controllers/HomeController.php', 'abc123'));

    expect($snapshot->state)->toBe(InspectionState::Available)
        ->and($snapshot->symbols)->toHaveCount(1)
        ->and($snapshot->symbols[0]->name)->toBe('HomeController')
        ->and($snapshot->symbols[0]->children[0]->name)->toBe('index')
        ->and($snapshot->symbols[0]->source->workspaceId)->toBe('ws_123')
        ->and($snapshot->symbols[0]->source->relativePath)->toBe('app/Http/Controllers/HomeController.php')
        ->and($snapshot->relationships)->toHaveCount(2)
        ->and($snapshot->revision)->toBe('abc123');
});

it('discovers Blade and Inertia resources across a workspace', function (): void {
    $root = sys_get_temp_dir().'/bindle-resources-'.bin2hex(random_bytes(4));
    mkdir($root.'/resources/views', 0777, true);
    mkdir($root.'/resources/js/Pages', 0777, true);
    file_put_contents($root.'/resources/views/home.blade.php', '<h1>Home</h1>');
    file_put_contents($root.'/resources/js/Pages/Home.vue', '<template />');

    $snapshot = (new PhpInspectionProvider)->inspect(new InspectionRequest('ws_456', $root));

    expect($snapshot->resources)->toHaveCount(2)
        ->and(array_map(fn ($resource) => $resource->kind, $snapshot->resources))->toBe(['inertia-page', 'blade'])
        ->and($snapshot->resources[0]->source->workspaceId)->toBe('ws_456');
});

it('distinguishes unavailable evidence from an inspected empty file', function (): void {
    $provider = new PhpInspectionProvider;
    $root = sys_get_temp_dir().'/bindle-empty-'.bin2hex(random_bytes(4));
    mkdir($root);
    file_put_contents($root.'/empty.php', '<?php');

    expect($provider->inspect(new InspectionRequest('ws', $root, 'empty.php'))->state)->toBe(InspectionState::Empty)
        ->and($provider->inspect(new InspectionRequest('ws', $root, 'missing.php'))->state)->toBe(InspectionState::Unavailable);
});

it('refreshes inspection evidence for one or many workspace requests', function (): void {
    $first = sys_get_temp_dir().'/bindle-first-'.bin2hex(random_bytes(4));
    $second = sys_get_temp_dir().'/bindle-second-'.bin2hex(random_bytes(4));
    mkdir($first);
    mkdir($second);
    file_put_contents($first.'/FirstController.php', '<?php class FirstController { public function index(): void {} }');
    file_put_contents($second.'/SecondController.php', '<?php class SecondController { public function show(): void {} }');

    $snapshots = (new InspectionService(new PhpInspectionProvider))->inspect([
        new InspectionRequest('ws_first', $first),
        new InspectionRequest('ws_second', $second),
    ]);

    expect($snapshots)->toHaveCount(2)
        ->and(array_map(fn ($snapshot) => $snapshot->workspaceId, $snapshots))->toBe(['ws_first', 'ws_second'])
        ->and($snapshots[0]->symbols[0]->name)->toBe('FirstController')
        ->and($snapshots[1]->symbols[0]->name)->toBe('SecondController');
});
