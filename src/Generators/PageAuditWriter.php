<?php

declare(strict_types=1);

namespace Maryeperry\Bindle\Generators;

use Maryeperry\Bindle\Storage\Models\Page;
use Maryeperry\Bindle\Storage\Models\PageComponent;

final class PageAuditWriter
{
    public function write(Page $page, string $outputDir): string
    {
        $path = $outputDir.'/'.$page->slug.'-audit.md';

        $rows = [];
        /** @var PageComponent $pc */
        foreach ($page->pageComponents()->with('component')->orderBy('depth')->get() as $pc) {
            $component = $pc->component;
            $propsJson = json_encode($pc->props_passed ?? new \stdClass);
            $rows[] = sprintf(
                '| `%s` | %s | %d | `%s` |',
                $component?->name ?? '?',
                $component?->kind ?? '?',
                $pc->depth,
                str_replace('|', '\\|', (string) $propsJson),
            );
        }

        $body = "# Page audit: `{$page->slug}`\n\n";
        $body .= "- Route: `{$page->route_name}` (`{$page->http_method} {$page->uri}`)\n";
        $body .= "- Framework: `{$page->framework}`\n";
        if ($page->controller) {
            $body .= "- Controller: `{$page->controller}`\n";
        }
        if ($page->view) {
            $body .= "- View: `{$page->view}`\n";
        }
        $body .= "\n## Components used on this page\n\n";
        if ($rows === []) {
            $body .= "_No components detected on this page._\n";
        } else {
            $body .= "| Component | Kind | Depth | Props passed |\n";
            $body .= "|---|---|---|---|\n";
            $body .= implode("\n", $rows)."\n";
        }

        @file_put_contents($path, $body);

        return $path;
    }
}
