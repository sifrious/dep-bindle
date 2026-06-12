<?php

declare(strict_types=1);

namespace Maryeperry\Bindle\Scanners;

use Illuminate\Contracts\Config\Repository;
use Maryeperry\Bindle\Scanners\Contracts\ComponentScanner;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use ReflectionProperty;
use SplFileInfo;

final class LivewireScanner implements ComponentScanner
{
    public function __construct(
        private readonly Repository $config,
    ) {}

    public function kind(): string
    {
        return 'livewire';
    }

    public function discover(): iterable
    {
        $livewirePath = (string) $this->config->get('bindle.paths.livewire', '');
        if ($livewirePath === '' || ! is_dir($livewirePath)) {
            return;
        }

        $iter = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($livewirePath));
        /** @var SplFileInfo $file */
        foreach ($iter as $file) {
            if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.php')) {
                continue;
            }

            $class = $this->fqcnFromFile($file->getPathname());
            if ($class === null || ! class_exists($class)) {
                continue;
            }

            $reflection = new ReflectionClass($class);
            if (! $this->isLivewireComponent($reflection)) {
                continue;
            }

            $props = [];
            foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $prop) {
                if ($prop->getDeclaringClass()->getName() !== $class) {
                    continue;
                }
                $type = $prop->getType();
                $default = $prop->hasDefaultValue() ? var_export($prop->getDefaultValue(), true) : null;
                $required = ! $prop->hasDefaultValue() && ($type === null || ! $type->allowsNull());

                $props[] = new DiscoveredProp(
                    name: $prop->getName(),
                    type: $type !== null ? (string) $type : null,
                    required: $required,
                    defaultValue: $default,
                    source: 'public-property',
                );
            }

            yield new DiscoveredComponent(
                name: $this->livewireAlias($class),
                kind: 'livewire',
                sourcePath: $file->getPathname(),
                props: $props,
                signatureHash: hash('xxh3', $class.':'.serialize(array_map(fn ($p) => $p->name, $props))),
            );
        }
    }

    public function callSitesIn(?string $viewName, ?string $renderedHtml): iterable
    {
        // Blade's <livewire:foo> and @livewire('foo') are picked up by BladeScanner.
        // For Inertia/SPA pages there is no Livewire call-site.
        return [];
    }

    private function isLivewireComponent(ReflectionClass $reflection): bool
    {
        $parent = $reflection;
        while ($parent !== false) {
            if ($parent->getName() === 'Livewire\\Component') {
                return true;
            }
            $parent = $parent->getParentClass();
        }

        return false;
    }

    private function fqcnFromFile(string $path): ?string
    {
        $source = (string) file_get_contents($path);
        $namespace = preg_match('/^namespace\\s+([^;]+);/m', $source, $m) === 1 ? trim($m[1]) : null;
        $className = preg_match('/^(?:final\\s+|abstract\\s+)?class\\s+(\\w+)/m', $source, $m) === 1 ? $m[1] : null;
        if ($className === null) {
            return null;
        }

        return $namespace !== null ? $namespace.'\\'.$className : $className;
    }

    private function livewireAlias(string $class): string
    {
        $tail = (string) (strrchr($class, '\\') ?: $class);

        return strtolower(trim(preg_replace('/[A-Z]/', '-$0', ltrim($tail, '\\')) ?? $tail, '-'));
    }
}
