<?php

declare(strict_types=1);

namespace Maryeperry\Bindle\Composition\Artifacts;

use InvalidArgumentException;
use Maryeperry\Bindle\Composition\Contracts\PageComposition;

final class WireframeGenerator
{
    /**
     * @param  PageComposition|array<string, mixed>  $composition
     * @return list<GeneratedArtifact>
     */
    public function generate(PageComposition|array $composition): array
    {
        $composition = $composition instanceof PageComposition ? $composition->toArray() : $composition;
        $id = $this->identifier($composition);
        $regions = $composition['regions'] ?? [];
        $stateLabels = [];
        $states = $composition['states'] ?? [];
        if (! is_array($states)) {
            throw new InvalidArgumentException('Composition states must be an array.');
        }
        foreach ($states as $state) {
            if (is_array($state) && is_string($state['id'] ?? null) && is_string($state['label'] ?? null)) {
                $stateLabels[$state['id']] = $state['label'];
            }
        }

        if (! is_array($regions) || ! array_is_list($regions)) {
            throw new InvalidArgumentException('Composition regions must be an ordered array.');
        }

        return array_map(
            fn (array $viewport): GeneratedArtifact => new GeneratedArtifact(
                "wireframes/{$id}-{$viewport['name']}.html",
                $this->render($id, $regions, $stateLabels, $viewport),
            ),
            [
                ['name' => 'desktop', 'width' => 1440],
                ['name' => 'mobile', 'width' => 390],
            ],
        );
    }

    /** @param array<string, mixed> $composition */
    private function identifier(array $composition): string
    {
        $id = $composition['id'] ?? $composition['name'] ?? null;

        if (! is_string($id) || preg_match('/\A[a-zA-Z0-9][a-zA-Z0-9._-]*\z/', $id) !== 1) {
            throw new InvalidArgumentException('Composition id must be a safe, non-empty identifier.');
        }

        return strtolower($id);
    }

    /**
     * @param  list<mixed>  $regions
     * @param  array<string, string>  $stateLabels
     * @param  array{name: string, width: int}  $viewport
     */
    private function render(string $id, array $regions, array $stateLabels, array $viewport): string
    {
        $sections = '';

        foreach ($regions as $region) {
            if (! is_array($region)) {
                throw new InvalidArgumentException('Every composition region must be an array.');
            }

            $label = $this->text($region['label'] ?? $region['id'] ?? 'Unnamed region');
            $ref = is_array($region['ref'] ?? null) ? $region['ref'] : [];
            $reference = $this->text($ref['id'] ?? $region['component_ref'] ?? $region['layout_ref'] ?? 'proposed');
            $status = $this->text($ref['status'] ?? $region['status'] ?? (isset($region['component_ref'], $region['layout_ref']) ? 'reuse' : 'proposal'));
            $behaviors = $this->strings($region['behavior_ids'] ?? $region['behaviors'] ?? []);
            $states = $this->strings($region['states'] ?? []);
            $states = array_map(fn (string $state): string => isset($stateLabels[$state]) ? $state.' ('.$stateLabels[$state].')' : $state, $states);
            $decisions = $this->strings($region['unresolved_decisions'] ?? $region['unresolved'] ?? []);

            $sections .= sprintf(
                "    <section data-region=\"%s\" data-status=\"%s\">\n      <h2>%s</h2>\n      <dl><dt>Realization</dt><dd>%s — %s</dd><dt>Behaviors</dt><dd>%s</dd><dt>States</dt><dd>%s</dd><dt>Unresolved</dt><dd>%s</dd></dl>\n    </section>\n",
                $this->escape($this->text($region['id'] ?? $label)),
                $this->escape($status),
                $this->escape($label),
                $this->escape($status),
                $this->escape($reference),
                $this->escape($behaviors === [] ? 'none' : implode(', ', $behaviors)),
                $this->escape($states === [] ? 'default' : implode(', ', $states)),
                $this->escape($decisions === [] ? 'none' : implode(', ', $decisions)),
            );
        }

        $title = $this->escape($id.' '.$viewport['name'].' wireframe');

        return <<<HTML
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>{$title}</title>
<style>body{font:16px system-ui;max-width:{$viewport['width']}px;margin:auto;padding:1rem}section{border:2px solid #334155;margin:1rem 0;padding:1rem}section[data-status="proposal"]{border-style:dashed}dt{font-weight:700}dd{margin:0 0 .5rem}</style></head>
<body data-composition="{$this->escape($id)}" data-viewport="{$viewport['name']}" data-width="{$viewport['width']}">
  <header><h1>{$title}</h1><p>Labeled planning artifact; not render evidence.</p></header>
  <main>
{$sections}  </main>
</body>
</html>
HTML;
    }

    private function text(mixed $value): string
    {
        return is_string($value) && $value !== '' ? $value : 'unknown';
    }

    /** @return list<string> */
    private function strings(mixed $values): array
    {
        if (! is_array($values)) {
            throw new InvalidArgumentException('Wireframe labels must be arrays of strings.');
        }

        $strings = [];
        foreach ($values as $value) {
            if (! is_string($value) || $value === '') {
                throw new InvalidArgumentException('Wireframe labels must be non-empty strings.');
            }
            $strings[] = $value;
        }

        sort($strings);

        return $strings;
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
