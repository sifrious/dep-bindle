<?php

declare(strict_types=1);

namespace Maryeperry\Bindle\Composition\Contracts;

final readonly class PageComposition
{
    public const string SCHEMA_VERSION = '1.0';

    /** @param array<string, mixed> $document */
    private function __construct(private array $document) {}

    /** @param array<string, mixed> $input */
    public static function fromArray(array $input, ?BehaviorStory $story = null): self
    {
        $errors = [];
        if (($input['schema_version'] ?? null) !== self::SCHEMA_VERSION) {
            $errors[] = 'schema_version must be '.self::SCHEMA_VERSION.'.';
        }
        if (! ContractRules::validId($input['id'] ?? null) || ! ContractRules::validId($input['story_id'] ?? null)) {
            $errors[] = 'id and story_id must be unambiguous identifiers.';
        }
        if ($story instanceof BehaviorStory && ($input['story_id'] ?? null) !== $story->id) {
            $errors[] = 'story_id does not match the supplied BehaviorStory.';
        }
        if (! in_array($input['framework'] ?? null, ['blade', 'livewire'], true)) {
            $errors[] = 'framework must be blade or livewire.';
        }

        $paths = $input['paths'] ?? null;
        if (! is_array($paths) || ! ContractRules::isList($paths) || $paths === [] || ContractRules::hasDuplicateStrings($paths) || array_filter($paths, fn (mixed $path): bool => ! ContractRules::safeRelativePath($path)) !== []) {
            $errors[] = 'paths must be a non-empty list of unique, traversal-safe relative paths.';
        }

        $states = $input['states'] ?? null;
        $stateIds = self::validateIdentifiedList($states, 'states', $errors);
        if (is_array($states)) {
            foreach ($states as $index => $state) {
                if (is_array($state) && (! is_string($state['label'] ?? null) || trim($state['label']) === '')) {
                    $errors[] = "states.$index.label must be a non-empty string.";
                }
            }
        }

        $regions = $input['regions'] ?? null;
        self::validateIdentifiedList($regions, 'regions', $errors);
        $mappedBehaviors = [];
        if (is_array($regions)) {
            foreach ($regions as $index => $region) {
                if (! is_array($region)) {
                    continue;
                }
                $ref = $region['ref'] ?? null;
                $status = is_array($ref) ? ($ref['status'] ?? null) : null;
                $reference = is_array($ref) ? ($ref['id'] ?? null) : null;
                if (! in_array($status, ['reuse', 'proposal'], true) || ! ContractRules::validId($reference)) {
                    $errors[] = "regions.$index.ref must contain a valid id and reuse or proposal status.";
                }
                $regionStates = $region['states'] ?? null;
                if (! is_array($regionStates) || ! ContractRules::isList($regionStates) || $regionStates === [] || array_filter($regionStates, fn (mixed $id): bool => ! is_string($id) || ! in_array($id, $stateIds, true)) !== []) {
                    $errors[] = "regions.$index.states must reference declared states.";
                }
                $behaviorIds = $region['behaviors'] ?? null;
                if (! is_array($behaviorIds) || ! ContractRules::isList($behaviorIds) || $behaviorIds === [] || array_filter($behaviorIds, fn (mixed $id): bool => ! ContractRules::validId($id)) !== []) {
                    $errors[] = "regions.$index.behaviors must be a non-empty list of behavior ids.";
                } else {
                    foreach ($behaviorIds as $behaviorId) {
                        if (is_string($behaviorId)) {
                            $mappedBehaviors[] = $behaviorId;
                        }
                    }
                }
            }
        }
        if (ContractRules::hasDuplicateStrings($mappedBehaviors)) {
            $errors[] = 'each behavior may map to only one region.';
        }
        if ($story instanceof BehaviorStory) {
            $expected = $story->behaviorIds();
            $unknown = array_values(array_diff($mappedBehaviors, $expected));
            $missing = array_values(array_diff($expected, $mappedBehaviors));
            if ($unknown !== []) {
                $errors[] = 'composition maps unknown behaviors: '.implode(', ', $unknown).'.';
            }
            if ($missing !== []) {
                $errors[] = 'composition does not map behaviors: '.implode(', ', $missing).'.';
            }
        }

        if ($errors !== []) {
            throw new ContractValidationException($errors);
        }

        return new self($input);
    }

    /**
     * @param  list<string>  $errors
     * @return list<string>
     */
    private static function validateIdentifiedList(mixed $items, string $name, array &$errors): array
    {
        if (! is_array($items) || ! ContractRules::isList($items) || $items === []) {
            $errors[] = "$name must be a non-empty ordered list.";

            return [];
        }
        $ids = [];
        foreach ($items as $index => $item) {
            $id = is_array($item) ? ($item['id'] ?? null) : null;
            if (! is_string($id) || ! ContractRules::validId($id)) {
                $errors[] = "$name.$index.id must be an unambiguous identifier.";
            } else {
                $ids[] = $id;
            }
        }
        if (ContractRules::hasDuplicateStrings($ids)) {
            $errors[] = "$name ids must be unique.";
        }

        return $ids;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->document;
    }
}
