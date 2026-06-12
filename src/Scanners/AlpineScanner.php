<?php

declare(strict_types=1);

namespace Maryeperry\Bindle\Scanners;

use Maryeperry\Bindle\Scanners\Contracts\ComponentScanner;

/**
 * Alpine.js components are inline — they don't exist as files. We treat each
 * unique `x-data="..."` invocation as a synthetic component.
 */
final class AlpineScanner implements ComponentScanner
{
    public function kind(): string
    {
        return 'alpine';
    }

    public function discover(): iterable
    {
        // Alpine components are emergent — discovery happens per page in callSitesIn().
        return [];
    }

    public function callSitesIn(?string $viewName, ?string $renderedHtml): iterable
    {
        if ($renderedHtml === null || $renderedHtml === '') {
            return;
        }

        // Match each element bearing x-data="..."
        if (! preg_match_all('/<([a-zA-Z][\\w-]*)\\b([^>]*?)\\sx-data\\s*=\\s*"([^"]*)"([^>]*)>/i', $renderedHtml, $matches, PREG_SET_ORDER)) {
            return;
        }

        $seen = [];
        foreach ($matches as $m) {
            $expr = trim($m[3]);
            $name = $this->nameForExpression($expr);
            if (isset($seen[$name])) {
                continue;
            }
            $seen[$name] = true;

            $props = $this->collectBindings($m[2].' '.$m[4]);
            yield new DiscoveredCallSite(
                componentName: $name,
                componentKind: 'alpine',
                propsPassed: $props,
            );
        }
    }

    private function nameForExpression(string $expr): string
    {
        if (preg_match('/^([a-zA-Z_][\\w]*)/', $expr, $m)) {
            return 'alpine-'.$m[1];
        }

        return 'alpine-inline-'.substr(hash('xxh3', $expr), 0, 8);
    }

    private function collectBindings(string $attrSegment): array
    {
        $props = [];
        if (preg_match_all('/(x-[a-zA-Z:-]+)(?:\\.(\\w+))*\\s*=\\s*"([^"]*)"/', $attrSegment, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                if ($m[1] === 'x-data') {
                    continue;
                }
                $props[(string) $m[1]] = (string) $m[3];
            }
        }

        return $props;
    }
}
