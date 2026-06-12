<?php

declare(strict_types=1);

namespace Maryeperry\Bindle\Scanners;

use Illuminate\Contracts\Config\Repository;
use Maryeperry\Bindle\Scanners\Contracts\ComponentScanner;
use Maryeperry\Bindle\Storage\ErrorLogger;

/**
 * Reads the Vite plugin's `bindle-manifest.json` and surfaces Vue/React/Svelte
 * components from it. If the manifest is missing, a warning is logged but
 * the scanner remains silent (no exception) so the rest of the pipeline runs.
 */
final class ManifestScanner implements ComponentScanner
{
    public function __construct(
        private readonly Repository $config,
        private readonly ErrorLogger $errors,
    ) {}

    public function kind(): string
    {
        // The manifest covers vue+react+svelte. discover() yields whichever
        // kinds the manifest contains.
        return 'vue|react|svelte';
    }

    public function discover(): iterable
    {
        $path = (string) $this->config->get('bindle.vite_manifest', '');
        if ($path === '' || ! is_file($path)) {
            $this->errors->warn(ErrorLogger::PHASE_SCAN, "Vite manifest not found at [{$path}]; Vue/React/Svelte components will not be discovered. Wire up maryeperry-vite-plugin-bindle and rebuild.", [
                'manifest_path' => $path,
            ]);

            return;
        }

        $raw = (string) file_get_contents($path);
        $json = json_decode($raw, true);
        if (! is_array($json) || ! isset($json['components']) || ! is_array($json['components'])) {
            $this->errors->warn(ErrorLogger::PHASE_SCAN, "Vite manifest at [{$path}] is malformed; expected a 'components' array.", []);

            return;
        }

        foreach ($json['components'] as $entry) {
            if (! is_array($entry)) {
                continue;
            }
            $name = (string) ($entry['name'] ?? '');
            $kind = (string) ($entry['kind'] ?? '');
            $file = (string) ($entry['file'] ?? '');
            if ($name === '' || ! in_array($kind, ['vue', 'react', 'svelte'], true)) {
                continue;
            }

            $props = [];
            foreach ((array) ($entry['props'] ?? []) as $prop) {
                if (! is_array($prop)) {
                    continue;
                }
                $props[] = new DiscoveredProp(
                    name: (string) ($prop['name'] ?? ''),
                    type: isset($prop['type']) ? (string) $prop['type'] : null,
                    required: (bool) ($prop['required'] ?? false),
                    defaultValue: isset($prop['default']) ? (string) $prop['default'] : null,
                    source: match ($kind) {
                        'vue' => 'define-props',
                        'react' => 'jsx-arg',
                        'svelte' => 'svelte-export',
                    },
                );
            }

            yield new DiscoveredComponent(
                name: $name,
                kind: $kind,
                sourcePath: $file !== '' ? $file : null,
                props: $props,
                signatureHash: hash('xxh3', $kind.':'.$name.':'.json_encode($props)),
            );
        }
    }

    public function callSitesIn(?string $viewName, ?string $renderedHtml): iterable
    {
        if ($renderedHtml === null) {
            return;
        }

        // Inertia mounts pages on a single root with a JSON payload. Each
        // detected `data-page` is one call-site.
        if (preg_match('/<div\\s+id="app"\\s+data-page=["\']([^"\']+)["\']/i', $renderedHtml, $m) === 1) {
            $payload = json_decode(htmlspecialchars_decode((string) $m[1]), true);
            if (is_array($payload)) {
                $componentName = (string) ($payload['component'] ?? '');
                $props = is_array($payload['props'] ?? null) ? $payload['props'] : [];
                if ($componentName !== '') {
                    yield new DiscoveredCallSite(
                        componentName: $componentName,
                        componentKind: 'inertia-page',
                        propsPassed: array_map(
                            fn ($v) => is_scalar($v) || $v === null ? $v : json_encode($v),
                            $props,
                        ),
                    );
                }
            }
        }
    }
}
