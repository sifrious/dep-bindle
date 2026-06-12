<?php

declare(strict_types=1);

use Maryeperry\Bindle\Scanners\DiscoveredComponent;
use Maryeperry\Bindle\Scanners\ManifestScanner;
use Maryeperry\Bindle\Storage\Database\DatabaseManager;
use Maryeperry\Bindle\Storage\ErrorLogger;
use Maryeperry\Bindle\Storage\Models\ErrorLog;

it('reads vue/react/svelte components from a Vite manifest', function (): void {
    $manifestPath = sys_get_temp_dir().'/bindle-test-manifest-'.uniqid().'.json';
    file_put_contents($manifestPath, json_encode([
        'generatedAt' => '2026-06-11T00:00:00.000Z',
        'components' => [
            ['file' => 'resources/js/Button.vue', 'name' => 'Button', 'kind' => 'vue',
                'props' => [['name' => 'label', 'type' => 'string', 'required' => true, 'default' => null]]],
            ['file' => 'resources/js/Card.tsx', 'name' => 'Card', 'kind' => 'react',
                'props' => [['name' => 'title', 'type' => null, 'required' => false, 'default' => null]]],
            ['file' => 'resources/js/Modal.svelte', 'name' => 'Modal', 'kind' => 'svelte',
                'props' => []],
        ],
    ]));

    config()->set('bindle.vite_manifest', $manifestPath);

    $scanner = new ManifestScanner(app('config'), app(ErrorLogger::class));
    $components = iterator_to_array($scanner->discover());

    expect($components)->toHaveCount(3);
    expect($components[0])->toBeInstanceOf(DiscoveredComponent::class);
    expect($components[0]->kind)->toBe('vue');
    expect($components[0]->name)->toBe('Button');
    expect($components[0]->props[0]->name)->toBe('label');
    expect($components[0]->props[0]->required)->toBeTrue();

    @unlink($manifestPath);
});

it('logs a warning instead of throwing when the manifest is missing', function (): void {
    config()->set('bindle.vite_manifest', '/nonexistent/bindle-manifest.json');

    $errors = app(ErrorLogger::class);
    $scanner = new ManifestScanner(app('config'), $errors);

    // Need the schema to exist for ErrorLog::create to work
    app(DatabaseManager::class)->ensureSchema();

    expect(iterator_to_array($scanner->discover()))->toBe([]);

    $warning = ErrorLog::query()->orderByDesc('id')->first();
    expect($warning?->severity)->toBe(ErrorLogger::SEVERITY_WARN);
    expect($warning?->message)->toContain('Vite manifest not found');
});
