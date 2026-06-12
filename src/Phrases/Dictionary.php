<?php

declare(strict_types=1);

namespace Maryeperry\Bindle\Phrases;

final class Dictionary
{
    /**
     * @param  array<string, array<string, string|array<string,string>>>  $tables
     */
    public function __construct(
        private readonly array $tables,
    ) {}

    public static function fromDefaults(): self
    {
        return new self([
            'page_layout' => require __DIR__.'/data/page_layout.php',
            'component_kind' => require __DIR__.'/data/component_kind.php',
            'prop_describe' => require __DIR__.'/data/prop_describe.php',
            'interaction_patterns' => require __DIR__.'/data/interaction_patterns.php',
        ]);
    }

    public function table(string $name): array
    {
        return $this->tables[$name] ?? [];
    }

    /**
     * Render a template string, substituting `{slot}` placeholders from $values.
     * Missing slots are replaced with sensible defaults (the slot name itself
     * is never leaked to output).
     */
    public function render(string $template, array $values): string
    {
        return preg_replace_callback('/\\{(\\w+)\\}/', function (array $m) use ($values): string {
            $key = (string) $m[1];
            $val = $values[$key] ?? '';
            if ($val === null || $val === '') {
                return $this->defaultFor($key);
            }

            return (string) $val;
        }, $template) ?? $template;
    }

    private function defaultFor(string $slot): string
    {
        return match ($slot) {
            'hero_summary' => 'a headline and supporting visual',
            'h1_phrase' => 'a primary heading',
            'field_count' => 'several',
            'row_count' => 'several',
            'paragraph_count' => 'a few',
            'form_purpose' => 'an unspecified action',
            'cta_phrase' => 'continue',
            'card_count' => 'several',
            default => 'a region',
        };
    }
}
