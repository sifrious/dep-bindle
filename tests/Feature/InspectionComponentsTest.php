<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;
use Maryeperry\Bindle\Inspection\Domain\CodeSymbol;
use Maryeperry\Bindle\Inspection\Domain\InspectionSnapshot;
use Maryeperry\Bindle\Inspection\Domain\InspectionState;
use Maryeperry\Bindle\Inspection\Domain\SourceLocation;
use Maryeperry\Bindle\Inspection\Domain\StructuralRelationship;
use Maryeperry\Bindle\Routes\ResolvedRoute;

function inspectionSnapshot(InspectionState $state = InspectionState::Available): InspectionSnapshot
{
    $source = new SourceLocation('ws_123', 'app/Example.php', 10, 20, '/source/example');

    return new InspectionSnapshot(
        'ws_123',
        'app/Example.php',
        $state,
        new DateTimeImmutable('2026-09-01T12:00:00+00:00'),
        [new CodeSymbol('class', 'Example', $source, children: [new CodeSymbol('method', 'run', $source)])],
        relationships: [new StructuralRelationship('Example', 'implements', 'Runnable', $source)],
    );
}

it('renders accessible symbol, outline, summary, and diagram evidence without javascript', function (): void {
    $snapshot = inspectionSnapshot();
    $html = Blade::render(<<<'BLADE'
<x-bindle::inspection.symbol-list id="symbols" :snapshot="$snapshot" />
<x-bindle::inspection.code-outline id="outline" :snapshot="$snapshot" />
<x-bindle::inspection.summary id="summary" :snapshot="$snapshot" />
<x-bindle::inspection.dependency-diagram id="diagram" :snapshot="$snapshot" />
BLADE, compact('snapshot'));

    expect($html)->toContain('<ul', '<li', '<article', '<details open', '<dl>', '<figure', '<figcaption', '<table')
        ->toContain('app/Example.php:10-20', 'Textual equivalent of the structural diagram', 'implements')
        ->not->toContain('role="tree"', '<script');
});

it('keeps empty stale and unavailable symbol states distinct', function (): void {
    $empty = new InspectionSnapshot('ws', '.', InspectionState::Empty, new DateTimeImmutable);
    $stale = new InspectionSnapshot('ws', '.', InspectionState::Stale, new DateTimeImmutable);
    $unavailable = new InspectionSnapshot('ws', '.', InspectionState::Unavailable, new DateTimeImmutable, message: 'Missing evidence.');

    expect(Blade::render('<x-bindle::inspection.symbol-list id="empty" :snapshot="$snapshot" />', ['snapshot' => $empty]))->toContain('No symbols were found')
        ->and(Blade::render('<x-bindle::inspection.symbol-list id="stale" :snapshot="$snapshot" />', ['snapshot' => $stale]))->toContain('evidence is stale')
        ->and(Blade::render('<x-bindle::inspection.symbol-list id="missing" :snapshot="$snapshot" />', ['snapshot' => $unavailable]))->toContain('Inspection unavailable', 'Missing evidence.');
});

it('renders routes components colocation findings and direct inspection navigation', function (): void {
    $routes = [new ResolvedRoute('home', '/', 'GET', 'HomeController', 'index', 'home', 'blade', [], [])];
    $components = [['name' => 'x-alert', 'kind' => 'blade', 'source' => 'resources/views/components/alert.blade.php']];
    $findings = [['title' => 'Controller and view', 'message' => 'Located together.', 'source' => 'app/Http/HomeController.php']];
    $tabs = ['symbols' => ['label' => 'Symbols', 'url' => '/inspect?view=symbols'], 'routes' => ['label' => 'Routes', 'url' => '/inspect?view=routes']];

    $html = Blade::render(<<<'BLADE'
<x-bindle::inspection.route-list id="routes" state="available" :routes="$routes" />
<x-bindle::inspection.component-inventory id="components" state="available" :components="$components" />
<x-bindle::inspection.colocation id="colocation" :findings="$findings" />
<x-bindle::inspection.tabs active="symbols" :tabs="$tabs" />
BLADE, compact('routes', 'components', 'findings', 'tabs'));

    expect($html)->toContain('<caption>Discovered application routes</caption>', 'HomeController@index')
        ->toContain('Bindle-discovered components', 'Unmatched')
        ->toContain('Located together.', 'aria-current="page"', '/inspect?view=routes')
        ->not->toContain('role="tablist"', '<script');
});
