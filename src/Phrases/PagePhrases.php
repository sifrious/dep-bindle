<?php

declare(strict_types=1);

namespace Maryeperry\Bindle\Phrases;

/**
 * Composes a 3–5 sentence layout description for a page from a deterministic
 * inspection of its rendered HTML. No randomness, no AI: the same page
 * produces the same description across runs.
 */
final class PagePhrases
{
    public function __construct(
        private readonly Dictionary $dict,
    ) {}

    public function compose(string $pageSlug, ?string $html, string $framework, array $detectedSignals = []): string
    {
        $fingerprint = $this->fingerprint($html ?? '', $framework, $detectedSignals);

        $opening = $this->pickTemplate('page_layout.opening', $pageSlug, $fingerprint['opening']);
        $middle = $this->pickTemplate('page_layout.middle', $pageSlug, $fingerprint['middle']);
        $closing = $this->pickTemplate('page_layout.closing', $pageSlug, $fingerprint['closing']);

        $sentences = [
            $this->dict->render($opening, $fingerprint['values']),
            $this->dict->render($middle, $fingerprint['values']),
            $this->dict->render($closing, $fingerprint['values']),
        ];

        foreach ($fingerprint['patterns'] as $patternKey) {
            $sentence = $this->dict->table('interaction_patterns')[$patternKey] ?? null;
            if (is_string($sentence)) {
                $sentences[] = $sentence;
            }
        }

        return implode(' ', $sentences);
    }

    /**
     * Inspect the HTML and return:
     *   ['opening' => candidate_keys, 'middle' => candidate_keys, 'closing' => candidate_keys,
     *    'values' => slot fill values, 'patterns' => interaction pattern keys]
     */
    private function fingerprint(string $html, string $framework, array $signals): array
    {
        $formCount = substr_count($html, '<form');
        $fieldCount = substr_count($html, '<input') + substr_count($html, '<textarea') + substr_count($html, '<select');
        $tableCount = substr_count($html, '<table');
        $rowCount = substr_count($html, '<tr');
        $cardCount = substr_count(strtolower($html), 'class="card');
        $paragraphCount = substr_count($html, '<p ');
        $hasHero = str_contains(strtolower($html), 'class="hero') || str_contains(strtolower($html), 'hero-');
        $hasHeader = str_contains($html, '<header') || str_contains($html, '<nav');
        $hasFooter = str_contains($html, '<footer');

        $opening = [];
        if ($framework === 'inertia') {
            $opening[] = 'spa_shell';
        }
        if ($hasHero) {
            $opening[] = 'fullwidth_hero';
        }
        if ($hasHeader) {
            $opening[] = 'simple_header';
        }
        $opening[] = 'plain';

        $middle = [];
        if ($formCount > 0 && $fieldCount >= 3) {
            $middle[] = 'form_heavy';
        }
        if ($tableCount > 0 && $rowCount >= 3) {
            $middle[] = 'table_heavy';
        }
        if ($cardCount >= 3) {
            $middle[] = 'card_grid';
        }
        if ($paragraphCount >= 3) {
            $middle[] = 'narrative';
        }
        $middle[] = 'mixed';

        $closing = [];
        if ($hasFooter) {
            $closing[] = 'footer_links';
        }
        if (str_contains(strtolower($html), 'sign up') || str_contains(strtolower($html), 'get started')) {
            $closing[] = 'cta_strip';
        }
        $closing[] = 'minimal';

        $patterns = [];
        if ($formCount > 0) {
            $patterns[] = 'has_form';
        }
        if ($tableCount > 0) {
            $patterns[] = 'has_table';
        }
        if (str_contains($html, 'wire:')) {
            $patterns[] = 'has_livewire';
        }
        if (str_contains($html, 'x-data')) {
            $patterns[] = 'has_alpine';
        }
        if ($framework === 'inertia') {
            $patterns[] = 'has_inertia';
        }
        if ($patterns === []) {
            $patterns[] = 'static';
        }

        foreach ($signals as $signal) {
            $patterns[] = (string) $signal;
        }

        return [
            'opening' => $opening,
            'middle' => $middle,
            'closing' => $closing,
            'patterns' => array_values(array_unique($patterns)),
            'values' => [
                'field_count' => (string) max(1, $fieldCount),
                'row_count' => (string) max(1, $rowCount),
                'paragraph_count' => (string) max(1, $paragraphCount),
                'card_count' => (string) max(1, $cardCount),
            ],
        ];
    }

    private function pickTemplate(string $table, string $pageSlug, array $candidates): string
    {
        [$tableName, $slot] = explode('.', $table);
        $bucket = $this->dict->table($tableName)[$slot] ?? [];

        foreach ($candidates as $candidate) {
            if (isset($bucket[$candidate])) {
                return (string) $bucket[$candidate];
            }
        }

        // Fallback to a stable per-slug pick.
        if ($bucket === []) {
            return '';
        }
        $keys = array_keys($bucket);
        $idx = crc32($pageSlug.':'.$tableName.':'.$slot) % count($keys);

        return (string) $bucket[$keys[$idx]];
    }
}
