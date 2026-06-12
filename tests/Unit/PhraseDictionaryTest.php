<?php

declare(strict_types=1);

use Maryeperry\Bindle\Phrases\Dictionary;
use Maryeperry\Bindle\Phrases\PagePhrases;
use Maryeperry\Bindle\Phrases\PropPhrases;
use Maryeperry\Bindle\Scanners\DiscoveredProp;

it('fills slots from values', function (): void {
    $dict = Dictionary::fromDefaults();
    expect($dict->render('A {count}-field form', ['count' => '4']))->toBe('A 4-field form');
});

it('replaces missing slots with defaults rather than leaking placeholders', function (): void {
    $dict = Dictionary::fromDefaults();
    $result = $dict->render('A {field_count}-field form', []);
    expect($result)->not->toContain('{');
    expect($result)->toContain('several');
});

it('describes a required typed prop deterministically', function (): void {
    $phrases = new PropPhrases(Dictionary::fromDefaults());

    $prop = new DiscoveredProp(
        name: 'label',
        type: 'string',
        required: true,
        defaultValue: null,
        source: 'define-props',
    );

    expect($phrases->describe($prop))->toBe('Accepts a required `string` prop named `label`.');
});

it('describes an optional prop with a default', function (): void {
    $phrases = new PropPhrases(Dictionary::fromDefaults());

    $prop = new DiscoveredProp(
        name: 'variant',
        type: 'string',
        required: false,
        defaultValue: "'primary'",
        source: 'define-props',
    );

    expect($phrases->describe($prop))->toBe("Accepts an optional `string` prop named `variant`, defaulting to `'primary'`.");
});

it('composes page descriptions deterministically for a given slug', function (): void {
    $pages = new PagePhrases(Dictionary::fromDefaults());

    $html = '<html><body><header></header><form><input/><input/><input/><input/></form><footer></footer></body></html>';
    $first = $pages->compose('checkout', $html, 'blade');
    $second = $pages->compose('checkout', $html, 'blade');

    expect($first)->toBe($second);
    expect($first)->not->toContain('{');
    expect($first)->toContain('form');
});
