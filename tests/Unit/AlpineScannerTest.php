<?php

declare(strict_types=1);

use Maryeperry\Bindle\Scanners\AlpineScanner;

it('treats unique x-data invocations as synthetic components', function (): void {
    $scanner = new AlpineScanner;

    $html = <<<'HTML'
        <div x-data="counter({ start: 0 })" x-on:click="increment">
            <span x-text="count"></span>
        </div>
        <div x-data="dropdown()" x-show="open">
            ...
        </div>
        <div x-data="counter({ start: 99 })">duplicate name, skipped</div>
    HTML;

    $sites = iterator_to_array($scanner->callSitesIn(null, $html));

    expect($sites)->toHaveCount(2);
    expect($sites[0]->componentName)->toBe('alpine-counter');
    expect($sites[1]->componentName)->toBe('alpine-dropdown');
});

it('returns no call sites when HTML lacks x-data', function (): void {
    $scanner = new AlpineScanner;
    expect(iterator_to_array($scanner->callSitesIn(null, '<html><body></body></html>')))->toBe([]);
});
