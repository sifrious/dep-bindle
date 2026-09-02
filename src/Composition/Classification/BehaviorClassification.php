<?php

declare(strict_types=1);

namespace Maryeperry\Bindle\Composition\Classification;

use InvalidArgumentException;

final readonly class BehaviorClassification
{
    public const array CATEGORIES = ['reuse', 'compose', 'backend-only', 'legacy', 'deferred', 'not-applicable'];

    /** @param list<array{id: string, description: string, category: string, rationale: string}> $entries */
    public function __construct(public array $entries)
    {
        if (count($entries) !== 117) {
            throw new InvalidArgumentException('The Burdgeon Git inventory must contain exactly 117 behaviors.');
        }

        $ids = [];
        foreach ($entries as $entry) {
            if (! in_array($entry['category'], self::CATEGORIES, true)) {
                throw new InvalidArgumentException("Unknown classification {$entry['category']}.");
            }
            if (isset($ids[$entry['id']]) || $entry['description'] === '' || $entry['rationale'] === '') {
                throw new InvalidArgumentException('Every behavior requires a unique ID, description, and rationale.');
            }
            $ids[$entry['id']] = true;
        }
    }

    public static function fromGitBehaviorMarkdown(string $markdown): self
    {
        preg_match_all('/^\|\s*(\d+)\s*\|\s*([^|]+?)\s*\|\s*([^|]+?)\s*\|/m', $markdown, $matches, PREG_SET_ORDER);
        $entries = [];
        foreach ($matches as $match) {
            $number = (int) $match[1];
            $description = trim($match[2]);
            $status = strtolower(trim($match[3]));
            $category = self::category($number, $description, $status);
            $entries[] = [
                'id' => 'GIT-'.str_pad((string) $number, 3, '0', STR_PAD_LEFT),
                'description' => $description,
                'category' => $category,
                'rationale' => self::rationale($category, $status),
            ];
        }

        return new self($entries);
    }

    private static function category(int $number, string $description, string $status): string
    {
        if ($number >= 108) {
            return 'compose';
        }

        $lower = strtolower($description);
        if (str_contains($lower, 'display') || str_starts_with($lower, 'view ') || str_starts_with($lower, 'show ') || str_contains($lower, 'browse ') || str_contains($lower, 'open and read') || str_contains($lower, 'checklist')) {
            return 'reuse';
        }

        if (str_contains($status, 'live dependency')) {
            return 'deferred';
        }

        if (str_contains($lower, 'pull-request comment') || str_contains($lower, 'approve or reject') || str_contains($lower, 'comment on')) {
            return 'legacy';
        }

        return 'backend-only';
    }

    private static function rationale(string $category, string $status): string
    {
        return match ($category) {
            'reuse' => 'Existing Burdgeon presentation can be reused by a composition.',
            'compose' => 'Requires a bounded ChangeStory composition; it is not satisfied by an existing screen alone.',
            'backend-only' => 'Service or data behavior has no independent UI requirement; no UI is invented.',
            'legacy' => 'Existing behavior remains available but is outside the ChangeStory vertical slice.',
            'deferred' => 'Requires live provider evidence that is unavailable to the deterministic local slice.',
            default => "Not applicable to the selected slice ({$status}).",
        };
    }
}
