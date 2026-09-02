<?php

declare(strict_types=1);

namespace Maryeperry\Bindle\Composition\Artifacts;

use InvalidArgumentException;
use Maryeperry\Bindle\Composition\Contracts\PageComposition;

final class AcceptanceTestGenerator
{
    /**
     * @param  PageComposition|array<string, mixed>  $composition
     * @return list<GeneratedArtifact>
     */
    public function generate(PageComposition|array $composition): array
    {
        $composition = $composition instanceof PageComposition ? $composition->toArray() : $composition;
        $id = $composition['id'] ?? null;
        $behaviors = $composition['selected_behaviors'] ?? $composition['behaviors'] ?? null;
        if ($behaviors === null && is_array($composition['regions'] ?? null)) {
            $behaviors = [];
            foreach ($composition['regions'] as $region) {
                if (is_array($region) && is_array($region['behaviors'] ?? null)) {
                    array_push($behaviors, ...$region['behaviors']);
                }
            }
        }
        if (! is_string($id) || preg_match('/\A[a-zA-Z0-9][a-zA-Z0-9._-]*\z/', $id) !== 1) {
            throw new InvalidArgumentException('Composition id must be a safe identifier.');
        }
        if (! is_array($behaviors) || $behaviors === []) {
            throw new InvalidArgumentException('At least one selected behavior is required.');
        }

        $mapped = [];
        foreach ($behaviors as $behavior) {
            $behaviorId = is_array($behavior) ? ($behavior['id'] ?? null) : $behavior;
            if (! is_string($behaviorId) || $behaviorId === '') {
                throw new InvalidArgumentException('Every selected behavior requires an id.');
            }
            $mapped[$behaviorId] = is_array($behavior) && is_string($behavior['title'] ?? null)
                ? $behavior['title'] : $behaviorId;
        }
        ksort($mapped);

        $tests = "<?php\n\ndeclare(strict_types=1);\n\n";
        foreach ($mapped as $behaviorId => $title) {
            $label = var_export("{$behaviorId}: {$title}", true);
            $tests .= "it({$label}, function (): void {\n    // Arrange the target application state for {$behaviorId}.\n    // Exercise the composed page and assert the observable outcome.\n})->group('bindle-generated', ".var_export($behaviorId, true).")->todo();\n\n";
        }

        $class = str_replace(' ', '', ucwords(str_replace(['-', '_', '.'], ' ', $id)));

        return [new GeneratedArtifact("tests/Feature/{$class}CompositionTest.php", $tests)];
    }
}
