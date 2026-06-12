<?php

declare(strict_types=1);

namespace Maryeperry\Bindle\Blade;

/**
 * Extracts @props, @include, @component, @livewire and <livewire:foo> usages
 * from raw Blade source. Output is callsite-shaped:
 *   ['kind' => 'include'|'component'|'livewire', 'name' => 'foo', 'props' => [...]]
 *
 * For @props([...]) we return ['kind' => 'props', 'props' => [...]] entries
 * representing the *definition* of an anonymous component's props.
 */
final class DirectiveParser
{
    /**
     * @return list<array{kind: string, name?: string, props: array<string, string|null>}>
     */
    public function parse(string $blade): array
    {
        $results = [];

        // @props([...])
        if (preg_match_all('/@props\\(\\s*\\[(.*?)\\]\\s*\\)/s', $blade, $m, PREG_SET_ORDER)) {
            foreach ($m as $match) {
                $results[] = ['kind' => 'props', 'props' => $this->parsePhpArrayKeys((string) $match[1])];
            }
        }

        // @include('view.name', ['k' => v])
        if (preg_match_all('/@include\\(\\s*[\'"]([^\'"]+)[\'"](?:\\s*,\\s*\\[(.*?)\\])?\\s*\\)/s', $blade, $m, PREG_SET_ORDER)) {
            foreach ($m as $match) {
                $results[] = [
                    'kind' => 'include',
                    'name' => (string) $match[1],
                    'props' => isset($match[2]) ? $this->parsePhpArrayKeys((string) $match[2]) : [],
                ];
            }
        }

        // @component('name', ['k' => v])
        if (preg_match_all('/@component\\(\\s*[\'"]([^\'"]+)[\'"](?:\\s*,\\s*\\[(.*?)\\])?\\s*\\)/s', $blade, $m, PREG_SET_ORDER)) {
            foreach ($m as $match) {
                $results[] = [
                    'kind' => 'component',
                    'name' => (string) $match[1],
                    'props' => isset($match[2]) ? $this->parsePhpArrayKeys((string) $match[2]) : [],
                ];
            }
        }

        // @livewire('name', ['k' => v])
        if (preg_match_all('/@livewire\\(\\s*[\'"]([^\'"]+)[\'"](?:\\s*,\\s*\\[(.*?)\\])?\\s*\\)/s', $blade, $m, PREG_SET_ORDER)) {
            foreach ($m as $match) {
                $results[] = [
                    'kind' => 'livewire',
                    'name' => (string) $match[1],
                    'props' => isset($match[2]) ? $this->parsePhpArrayKeys((string) $match[2]) : [],
                ];
            }
        }

        // <livewire:foo :bar="x" />
        if (preg_match_all('/<livewire:([a-zA-Z0-9._:-]+)([^>]*?)\\s*\\/?>/s', $blade, $m, PREG_SET_ORDER)) {
            $tagParser = new ComponentTagParser;
            foreach ($m as $match) {
                $synthetic = '<x-stub'.$match[2].'/>';
                $parsed = $tagParser->parse($synthetic);
                $props = $parsed[0]['props'] ?? [];
                $results[] = [
                    'kind' => 'livewire',
                    'name' => str_replace([':', '.'], '-', (string) $match[1]),
                    'props' => $props,
                ];
            }
        }

        return $results;
    }

    /**
     * Extract array keys from a Blade-array-literal substring.
     *
     * Given `'foo' => 1, 'bar' => $user`, returns ['foo' => '1', 'bar' => '$user'].
     * Best-effort — does not handle nested arrays or multi-line expressions.
     *
     * @return array<string, string|null>
     */
    private function parsePhpArrayKeys(string $arrayBody): array
    {
        $out = [];

        if (preg_match_all('/[\'"]([^\'"]+)[\'"]\\s*=>\\s*([^,\\]]+)/', $arrayBody, $m, PREG_SET_ORDER)) {
            foreach ($m as $match) {
                $out[(string) $match[1]] = trim((string) $match[2]);
            }
        }

        return $out;
    }
}
