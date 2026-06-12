<?php

declare(strict_types=1);

namespace Maryeperry\Bindle\Scanners;

use Illuminate\Contracts\Config\Repository;
use Maryeperry\Bindle\Scanners\Contracts\ComponentScanner;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Walks controller files looking for `Inertia::render('Page/Name', [...])`
 * calls. Each call becomes an inertia-page "component" whose props are the
 * string keys of the array literal.
 */
final class InertiaScanner implements ComponentScanner
{
    public function __construct(
        private readonly Repository $config,
    ) {}

    public function kind(): string
    {
        return 'inertia-page';
    }

    public function discover(): iterable
    {
        $controllers = (string) $this->config->get('bindle.paths.controllers', '');
        if ($controllers === '' || ! is_dir($controllers)) {
            return;
        }

        $iter = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($controllers));
        /** @var SplFileInfo $file */
        foreach ($iter as $file) {
            if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.php')) {
                continue;
            }

            $source = (string) file_get_contents($file->getPathname());

            if (! preg_match_all(
                '/Inertia::render\\(\\s*[\'"]([^\'"]+)[\'"](?:\\s*,\\s*\\[(.*?)\\])?\\s*\\)/s',
                $source,
                $matches,
                PREG_SET_ORDER,
            )) {
                continue;
            }

            foreach ($matches as $m) {
                $pageName = (string) $m[1];
                $propsBody = (string) ($m[2] ?? '');
                $props = [];

                if (preg_match_all('/[\'"]([^\'"]+)[\'"]\\s*=>/', $propsBody, $pm)) {
                    foreach ($pm[1] as $propName) {
                        $props[] = new DiscoveredProp(
                            name: (string) $propName,
                            type: null,
                            required: false,
                            defaultValue: null,
                            source: 'inertia-render',
                        );
                    }
                }

                yield new DiscoveredComponent(
                    name: $pageName,
                    kind: 'inertia-page',
                    sourcePath: $file->getPathname(),
                    props: $props,
                    signatureHash: hash('xxh3', $pageName.':'.$propsBody),
                );
            }
        }
    }

    public function callSitesIn(?string $viewName, ?string $renderedHtml): iterable
    {
        // Inertia pages are referenced by name; the page-to-component link is
        // established by ScanPipeline from the route's `view` field.
        return [];
    }
}
