<?php

declare(strict_types=1);

namespace Maryeperry\Bindle\Blade;

/**
 * Extracts `<x-foo :prop="..." plain="...">` component tags from raw Blade source.
 *
 * Returns a list of ['name' => 'foo', 'props' => ['key' => 'string-expression']]
 * shaped arrays. Self-closing and paired tags both work. Slot content is not
 * extracted (component graph is what we care about here).
 */
final class ComponentTagParser
{
    /**
     * @return list<array{name: string, props: array<string, string>}>
     */
    public function parse(string $blade): array
    {
        $results = [];
        $pattern = '/<x-([a-zA-Z0-9._:-]+)([^>]*?)\s*\/?>/s';

        if (! preg_match_all($pattern, $blade, $matches, PREG_SET_ORDER)) {
            return $results;
        }

        foreach ($matches as $match) {
            $name = str_replace([':', '.'], '-', $match[1]);
            $props = $this->parseAttributes((string) $match[2]);
            $results[] = ['name' => $name, 'props' => $props];
        }

        return $results;
    }

    /**
     * @return array<string, string>
     */
    private function parseAttributes(string $segment): array
    {
        $props = [];
        // Matches `:bound="expr"`, `attr="value"`, `attr='value'`, `flag`
        $attrPattern = '/(?:(:?[a-zA-Z_@\\-:][\\w\\-:.]*)(?:\\s*=\\s*(?:"([^"]*)"|\'([^\']*)\'))?)/';

        if (! preg_match_all($attrPattern, $segment, $matches, PREG_SET_ORDER)) {
            return $props;
        }

        foreach ($matches as $m) {
            $rawName = $m[1] ?? '';
            if ($rawName === '') {
                continue;
            }
            $name = ltrim($rawName, ':');
            // Skip Laravel-special attributes that aren't user props.
            if ($name === '' || str_starts_with($name, '@')) {
                continue;
            }

            $value = $m[2] ?? ($m[3] ?? '');
            $props[$name] = $value;
        }

        return $props;
    }
}
