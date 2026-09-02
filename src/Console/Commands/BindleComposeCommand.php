<?php

declare(strict_types=1);

namespace Maryeperry\Bindle\Console\Commands;

use Illuminate\Console\Command;
use JsonException;
use Maryeperry\Bindle\Composition\Artifacts\AcceptanceTestGenerator;
use Maryeperry\Bindle\Composition\Artifacts\GeneratedArtifact;
use Maryeperry\Bindle\Composition\Artifacts\WireframeGenerator;
use Maryeperry\Bindle\Composition\Catalog\CatalogSnapshot;
use Maryeperry\Bindle\Composition\Contracts\BehaviorStory;
use Maryeperry\Bindle\Composition\Contracts\PageComposition;
use Maryeperry\Bindle\Composition\Planning\ReuseFirstRealizationPlanner;
use Maryeperry\Bindle\Support\Environment;
use Throwable;

final class BindleComposeCommand extends Command
{
    protected $signature = 'bindle:compose
                            {story : Path to a BehaviorStory JSON document}
                            {composition : Path to a PageComposition JSON document}
                            {--catalog= : Optional scan-catalog JSON snapshot}';

    protected $description = 'Validate a composition and print a deterministic reuse-first dry-run plan.';

    public function handle(
        Environment $environment,
        ReuseFirstRealizationPlanner $planner,
        WireframeGenerator $wireframes,
        AcceptanceTestGenerator $tests,
    ): int {
        $environment->assertSafe();

        try {
            $storyPath = $this->argument('story');
            $compositionPath = $this->argument('composition');
            if (! is_string($storyPath) || ! is_string($compositionPath)) {
                throw new JsonException('Story and composition arguments must be file paths.');
            }
            $story = BehaviorStory::fromArray($this->document($storyPath));
            $composition = PageComposition::fromArray($this->document($compositionPath), $story);
            $catalogDocument = $this->option('catalog');
            $catalog = is_string($catalogDocument) && $catalogDocument !== ''
                ? $this->catalog($this->document($catalogDocument))
                : new CatalogSnapshot;
            $plan = $planner->plan($composition, $catalog);

            $payload = [
                'mode' => 'dry-run',
                'story_id' => $story->id,
                'composition_id' => $composition->toArray()['id'],
                'plan' => $plan->toArray(),
                'artifacts' => array_map(
                    static fn (GeneratedArtifact $artifact): array => ['path' => $artifact->path, 'sha256' => hash('sha256', $artifact->contents)],
                    [...$wireframes->generate($composition), ...$tests->generate($composition)],
                ),
            ];
            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    /** @return array<string, mixed> */
    private function document(string $path): array
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new JsonException("Unable to read JSON document: {$path}");
        }
        $document = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($document) || array_is_list($document)) {
            throw new JsonException("JSON document must contain an object: {$path}");
        }

        $object = [];
        foreach ($document as $key => $value) {
            if (! is_string($key)) {
                throw new JsonException("JSON object contains a non-string key: {$path}");
            }
            $object[$key] = $value;
        }

        return $object;
    }

    /** @param array<string, mixed> $document */
    private function catalog(array $document): CatalogSnapshot
    {
        $layouts = $this->records($document['layouts'] ?? []);
        $components = $this->records($document['components'] ?? []);
        $routes = $this->records($document['routes'] ?? []);
        $rawStyling = $document['styling'] ?? [];
        $styling = [];
        if (! is_array($rawStyling) || array_is_list($rawStyling) && $rawStyling !== []) {
            throw new JsonException('Catalog styling must be an object.');
        }
        foreach ($rawStyling as $key => $value) {
            if (! is_string($key)) {
                throw new JsonException('Catalog styling keys must be strings.');
            }
            $styling[$key] = $value;
        }

        return new CatalogSnapshot(
            $layouts,
            $components,
            $routes,
            $styling,
        );
    }

    /** @return list<array<string, mixed>> */
    private function records(mixed $records): array
    {
        if (! is_array($records) || ! array_is_list($records)) {
            throw new JsonException('Catalog collections must be arrays.');
        }
        $normalized = [];
        foreach ($records as $record) {
            if (! is_array($record) || array_is_list($record)) {
                throw new JsonException('Catalog entries must be objects.');
            }
            $entry = [];
            foreach ($record as $key => $value) {
                if (! is_string($key)) {
                    throw new JsonException('Catalog entry keys must be strings.');
                }
                $entry[$key] = $value;
            }
            $normalized[] = $entry;
        }

        return $normalized;
    }
}
