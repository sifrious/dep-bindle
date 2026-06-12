<?php

declare(strict_types=1);

namespace Maryeperry\Bindle\Scanners;

use Illuminate\Contracts\Config\Repository;
use Maryeperry\Bindle\Blade\ComponentTagParser;
use Maryeperry\Bindle\Blade\DirectiveParser;
use Maryeperry\Bindle\Scanners\Contracts\ComponentScanner;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class BladeScanner implements ComponentScanner
{
    public function __construct(
        private readonly Repository $config,
        private readonly ComponentTagParser $tags = new ComponentTagParser,
        private readonly DirectiveParser $directives = new DirectiveParser,
    ) {}

    public function kind(): string
    {
        return 'blade-anon';
    }

    public function discover(): iterable
    {
        $viewsPath = (string) $this->config->get('bindle.paths.views', '');
        if ($viewsPath === '' || ! is_dir($viewsPath)) {
            return;
        }

        $componentsPath = rtrim($viewsPath, '/').'/components';
        if (! is_dir($componentsPath)) {
            return;
        }

        $iter = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($componentsPath));
        /** @var SplFileInfo $file */
        foreach ($iter as $file) {
            if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $blade = (string) file_get_contents($file->getPathname());
            $name = $this->componentNameFromPath($file->getPathname(), $componentsPath);

            $props = [];
            foreach ($this->directives->parse($blade) as $entry) {
                if (($entry['kind'] ?? null) !== 'props') {
                    continue;
                }
                foreach ($entry['props'] as $propName => $defaultExpr) {
                    $required = $defaultExpr === null || trim((string) $defaultExpr) === '';
                    $props[] = new DiscoveredProp(
                        name: $propName,
                        type: null,
                        required: $required,
                        defaultValue: $required ? null : (string) $defaultExpr,
                        source: 'props-directive',
                    );
                }
            }

            yield new DiscoveredComponent(
                name: $name,
                kind: 'blade-anon',
                sourcePath: $file->getPathname(),
                props: $props,
                signatureHash: hash('xxh3', $blade),
            );
        }
    }

    public function callSitesIn(?string $viewName, ?string $renderedHtml): iterable
    {
        if ($viewName === null) {
            return;
        }
        $viewsPath = (string) $this->config->get('bindle.paths.views', '');
        if ($viewsPath === '') {
            return;
        }

        $bladePath = rtrim($viewsPath, '/').'/'.str_replace('.', '/', $viewName).'.blade.php';
        if (! is_file($bladePath)) {
            return;
        }

        $blade = (string) file_get_contents($bladePath);

        foreach ($this->tags->parse($blade) as $tag) {
            yield new DiscoveredCallSite(
                componentName: $tag['name'],
                componentKind: 'blade-anon',
                propsPassed: $tag['props'],
            );
        }

        foreach ($this->directives->parse($blade) as $entry) {
            $kind = $entry['kind'] ?? null;
            if (! in_array($kind, ['include', 'component', 'livewire'], true)) {
                continue;
            }
            yield new DiscoveredCallSite(
                componentName: (string) ($entry['name'] ?? ''),
                componentKind: $kind === 'livewire' ? 'livewire' : 'blade-anon',
                propsPassed: array_map(fn ($v) => is_string($v) ? $v : (string) $v, $entry['props']),
            );
        }
    }

    private function componentNameFromPath(string $absolute, string $componentsRoot): string
    {
        $rel = ltrim(str_replace($componentsRoot, '', $absolute), '/');
        $rel = preg_replace('/\\.blade\\.php$/', '', $rel) ?? $rel;

        return str_replace('/', '-', (string) $rel);
    }
}
