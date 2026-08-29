<?php

declare(strict_types=1);

namespace Maryeperry\Bindle\Composition\Catalog;

use InvalidArgumentException;

final readonly class CatalogSnapshot
{
    /**
     * @param  list<array<string, mixed>>  $layouts
     * @param  list<array<string, mixed>>  $components
     * @param  list<array<string, mixed>>  $routes
     * @param  array<string, mixed>  $styling
     */
    public function __construct(
        public array $layouts = [],
        public array $components = [],
        public array $routes = [],
        public array $styling = [],
    ) {
        $this->assertUniqueReferences($layouts, 'layout');
        $this->assertUniqueReferences($components, 'component');
    }

    /** @return array<string, mixed>|null */
    public function layout(string $reference): ?array
    {
        return $this->find($this->layouts, $reference);
    }

    /** @return array<string, mixed>|null */
    public function component(string $reference): ?array
    {
        return $this->find($this->components, $reference);
    }

    public function hasReference(string $reference): bool
    {
        if ($this->layout($reference) !== null) {
            return true;
        }

        return $this->component($reference) !== null;
    }

    /** @return array{layouts: list<array<string, mixed>>, components: list<array<string, mixed>>, routes: list<array<string, mixed>>, styling: array<string, mixed>} */
    public function toArray(): array
    {
        return [
            'layouts' => $this->sorted($this->layouts),
            'components' => $this->sorted($this->components),
            'routes' => $this->sorted($this->routes, 'uri'),
            'styling' => $this->styling,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return array<string, mixed>|null
     */
    private function find(array $items, string $reference): ?array
    {
        foreach ($items as $item) {
            if (($item['ref'] ?? null) === $reference) {
                return $item;
            }
        }

        return null;
    }

    /** @param list<array<string, mixed>> $items */
    private function assertUniqueReferences(array $items, string $kind): void
    {
        $seen = [];
        foreach ($items as $item) {
            $reference = $item['ref'] ?? null;
            if (! is_string($reference) || $reference === '') {
                throw new InvalidArgumentException("Every {$kind} must have a non-empty ref.");
            }
            if (isset($seen[$reference])) {
                throw new InvalidArgumentException("Duplicate {$kind} ref: {$reference}.");
            }
            $seen[$reference] = true;
        }
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private function sorted(array $items, string $fallback = 'ref'): array
    {
        usort($items, static function (array $left, array $right) use ($fallback): int {
            $leftKey = $left['ref'] ?? $left[$fallback] ?? '';
            $rightKey = $right['ref'] ?? $right[$fallback] ?? '';

            return strcmp(is_string($leftKey) ? $leftKey : '', is_string($rightKey) ? $rightKey : '');
        });

        return $items;
    }
}
