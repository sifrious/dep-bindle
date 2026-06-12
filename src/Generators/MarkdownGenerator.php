<?php

declare(strict_types=1);

namespace Maryeperry\Bindle\Generators;

use Illuminate\Contracts\Config\Repository;
use Maryeperry\Bindle\Storage\ErrorLogger;
use Maryeperry\Bindle\Storage\Models\Component;
use Maryeperry\Bindle\Storage\Models\Page;
use Maryeperry\Bindle\Storage\Models\Run;

final class MarkdownGenerator
{
    public function __construct(
        private readonly Repository $config,
        private readonly PageAuditWriter $pageAudit,
        private readonly PageDescriptionWriter $pageDescription,
        private readonly ComponentDetailWriter $componentDetail,
        private readonly ComponentPageAuditWriter $componentPageAudit,
        private readonly ErrorLogger $errors,
    ) {}

    /**
     * @param  array<int, string>  $renderedHtmlByPageId
     */
    public function generate(Run $run, array $renderedHtmlByPageId = []): array
    {
        $outputRoot = rtrim((string) $this->config->get('bindle.output_path'), '/');
        $written = [];

        /** @var Page $page */
        foreach ($run->pages as $page) {
            $pageDir = $outputRoot.'/pages/'.$page->slug;
            if (! is_dir($pageDir)) {
                @mkdir($pageDir, 0o755, true);
            }
            try {
                $written[] = $this->pageAudit->write($page, $pageDir);
                $written[] = $this->pageDescription->write($page, $renderedHtmlByPageId[$page->id] ?? null, $pageDir);
            } catch (\Throwable $e) {
                $this->errors->error(ErrorLogger::PHASE_MARKDOWN, "Failed to write page markdown for {$page->slug}: {$e->getMessage()}", [
                    'subject_type' => 'page',
                    'subject_id' => $page->id,
                ]);
            }
        }

        /** @var Component $component */
        foreach ($run->components as $component) {
            $componentDir = $outputRoot.'/components/'.$component->slug;
            if (! is_dir($componentDir)) {
                @mkdir($componentDir, 0o755, true);
            }
            try {
                $written[] = $this->componentDetail->write($component, $componentDir);
                $written[] = $this->componentPageAudit->write($component, $componentDir);
            } catch (\Throwable $e) {
                $this->errors->error(ErrorLogger::PHASE_MARKDOWN, "Failed to write component markdown for {$component->slug}: {$e->getMessage()}", [
                    'subject_type' => 'component',
                    'subject_id' => $component->id,
                ]);
            }
        }

        $written[] = $this->writeIndex($run, $outputRoot);

        return array_values(array_filter($written));
    }

    private function writeIndex(Run $run, string $outputRoot): string
    {
        $path = $outputRoot.'/bindle.md';
        if (! is_dir($outputRoot)) {
            @mkdir($outputRoot, 0o755, true);
        }

        $body = "# Bindle audit — run #{$run->id}\n\n";
        $body .= "- Environment: `{$run->environment}`\n";
        $body .= '- Started at: `'.(string) $run->started_at."`\n";
        $body .= "- Bindle version: `{$run->bindle_version}`\n\n";

        $body .= "## Pages\n\n";
        foreach ($run->pages as $page) {
            $body .= "- [`{$page->slug}`](pages/{$page->slug}/{$page->slug}-description.md) — `{$page->http_method} {$page->uri}` ({$page->framework})\n";
        }

        $body .= "\n## Components\n\n";
        foreach ($run->components as $component) {
            $body .= "- [`{$component->name}`](components/{$component->slug}/{$component->slug}-detail.md) — {$component->kind}\n";
        }

        @file_put_contents($path, $body);

        return $path;
    }
}
