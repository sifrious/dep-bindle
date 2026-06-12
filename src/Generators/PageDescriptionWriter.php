<?php

declare(strict_types=1);

namespace Maryeperry\Bindle\Generators;

use Maryeperry\Bindle\Phrases\PagePhrases;
use Maryeperry\Bindle\Storage\Models\Page;

final class PageDescriptionWriter
{
    public function __construct(
        private readonly PagePhrases $phrases,
    ) {}

    public function write(Page $page, ?string $renderedHtml, string $outputDir): string
    {
        $path = $outputDir.'/'.$page->slug.'-description.md';

        $description = $this->phrases->compose($page->slug, $renderedHtml, $page->framework);

        $body = "# Page description: `{$page->slug}`\n\n";
        $body .= "_This description is generated from structural inspection of the rendered page — no AI was used. Slot-filled deterministically from a phrase dictionary._\n\n";
        $body .= $description."\n";

        @file_put_contents($path, $body);

        return $path;
    }
}
