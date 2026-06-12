<?php

declare(strict_types=1);

namespace Maryeperry\Bindle\Generators;

use Maryeperry\Bindle\Storage\Models\Component;
use Maryeperry\Bindle\Storage\Models\PageComponent;

final class ComponentPageAuditWriter
{
    public function write(Component $component, string $outputDir): string
    {
        $path = $outputDir.'/'.$component->slug.'-page-audit.md';

        $rows = [];
        /** @var PageComponent $pc */
        foreach ($component->pageComponents()->with(['page', 'parent'])->get() as $pc) {
            $rows[] = sprintf(
                '| `%s` | `%s %s` | `%s` | `%s` | %d |',
                $pc->page?->slug ?? '?',
                $pc->page?->http_method ?? '?',
                $pc->page?->uri ?? '?',
                $pc->page?->view ?? '',
                $pc->parent?->name ?? '',
                $pc->depth,
            );
        }

        $body = "# Component page-audit: `{$component->name}`\n\n";
        $body .= "Every route, view, and parent component that references `{$component->name}` in this run.\n\n";
        if ($rows === []) {
            $body .= "_This component was defined but no on-page usages were detected. It may be used dynamically or be unreachable from the enumerated routes._\n";
        } else {
            $body .= "| Page slug | Route | View | Parent component | Depth |\n";
            $body .= "|---|---|---|---|---|\n";
            $body .= implode("\n", $rows)."\n";
        }

        @file_put_contents($path, $body);

        return $path;
    }
}
