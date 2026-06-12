<?php

declare(strict_types=1);

use Maryeperry\Bindle\Blade\ComponentTagParser;
use Maryeperry\Bindle\Blade\DirectiveParser;

it('extracts <x-foo :prop="..."> tags with their props', function (): void {
    $blade = <<<'BLADE'
        <x-button :label="$title" type="primary" />
        <x-card.body>...</x-card.body>
    BLADE;

    $parsed = (new ComponentTagParser)->parse($blade);

    expect($parsed)->toHaveCount(2);
    expect($parsed[0]['name'])->toBe('button');
    expect($parsed[0]['props'])->toMatchArray(['label' => '$title', 'type' => 'primary']);
    expect($parsed[1]['name'])->toBe('card-body');
});

it('extracts @props() definitions from anonymous components', function (): void {
    $blade = "@props(['label' => 'Submit', 'required' => true])";

    $parsed = (new DirectiveParser)->parse($blade);

    expect($parsed)->toHaveCount(1);
    expect($parsed[0]['kind'])->toBe('props');
    expect($parsed[0]['props'])->toMatchArray(['label' => "'Submit'", 'required' => 'true']);
});

it('extracts @include directives', function (): void {
    $blade = "@include('partials.header', ['title' => 'Home'])";

    $parsed = (new DirectiveParser)->parse($blade);

    expect($parsed)->toHaveCount(1);
    expect($parsed[0])->toMatchArray([
        'kind' => 'include',
        'name' => 'partials.header',
    ]);
    expect($parsed[0]['props'])->toMatchArray(['title' => "'Home'"]);
});

it('extracts <livewire:foo /> tags', function (): void {
    $blade = '<livewire:counter :start="10" />';

    $parsed = (new DirectiveParser)->parse($blade);

    expect($parsed)->toHaveCount(1);
    expect($parsed[0])->toMatchArray([
        'kind' => 'livewire',
        'name' => 'counter',
    ]);
});
