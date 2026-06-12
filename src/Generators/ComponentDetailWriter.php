<?php

declare(strict_types=1);

namespace Maryeperry\Bindle\Generators;

use Maryeperry\Bindle\Phrases\ComponentPhrases;
use Maryeperry\Bindle\Storage\Models\Component;

final class ComponentDetailWriter
{
    public function __construct(
        private readonly ComponentPhrases $phrases,
    ) {}

    public function write(Component $component, string $outputDir): string
    {
        $path = $outputDir.'/'.$component->slug.'-detail.md';

        $body = "# Component detail: `{$component->name}`\n\n";
        $body .= "- Kind: `{$component->kind}`\n";
        if ($component->source_path) {
            $body .= "- Source: `{$component->source_path}`\n";
        }
        $body .= "\n## Description\n\n";
        $body .= $this->phrases->describe($component)."\n";

        $variants = $component->variants;
        if ($variants->isNotEmpty()) {
            $body .= "\n## Variants\n\n";
            foreach ($variants as $variant) {
                $body .= "- **{$variant->variant_name}** — `".json_encode($variant->props_combo)."`\n";
            }
        }

        $props = $component->props;
        if ($props->isNotEmpty()) {
            $body .= "\n## Prop signatures\n\n";
            $body .= "| Name | Type | Required | Default | Source |\n";
            $body .= "|---|---|---|---|---|\n";
            foreach ($props as $prop) {
                $body .= sprintf(
                    "| `%s` | `%s` | %s | `%s` | %s |\n",
                    $prop->name,
                    $prop->type ?? '',
                    $prop->required ? 'yes' : 'no',
                    $prop->default_value ?? '',
                    $prop->source,
                );
            }
        }

        @file_put_contents($path, $body);

        return $path;
    }
}
