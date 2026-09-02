<?php

declare(strict_types=1);

namespace Maryeperry\Bindle\Composition\Contracts;

final readonly class BehaviorStory
{
    public const string SCHEMA_VERSION = '1.0';

    /**
     * @param  array{kind: 'git-behavior', path: string}  $source
     * @param  array{root: string, paths: list<string>}  $target
     * @param  list<array{id: string, order: int, title: string, given: list<string>, when: list<string>, then: list<string>}>  $behaviors
     */
    private function __construct(
        public string $id,
        public string $title,
        public array $source,
        public array $target,
        public array $behaviors,
    ) {}

    /** @param array<string, mixed> $input */
    public static function fromArray(array $input): self
    {
        $errors = [];
        if (($input['schema_version'] ?? null) !== self::SCHEMA_VERSION) {
            $errors[] = 'schema_version must be '.self::SCHEMA_VERSION.'.';
        }
        if (! ContractRules::validId($input['id'] ?? null)) {
            $errors[] = 'id must be an unambiguous identifier.';
        }
        if (! is_string($input['title'] ?? null) || trim($input['title']) === '') {
            $errors[] = 'title must be a non-empty string.';
        }

        $source = $input['source'] ?? null;
        if (! is_array($source) || ($source['kind'] ?? null) !== 'git-behavior' || ! ContractRules::safeAbsolutePath($source['path'] ?? null)) {
            $errors[] = 'source must identify a git-behavior document by a safe absolute path.';
        }

        $target = $input['target'] ?? null;
        if (! is_array($target) || ! ContractRules::safeAbsolutePath($target['root'] ?? null)) {
            $errors[] = 'target.root must be a safe absolute application path.';
        }
        $paths = is_array($target) ? ($target['paths'] ?? null) : null;
        if (! is_array($paths) || ! ContractRules::isList($paths) || $paths === []) {
            $errors[] = 'target.paths must be a non-empty ordered list.';
        } elseif (ContractRules::hasDuplicateStrings($paths) || array_filter($paths, fn (mixed $path): bool => ! ContractRules::safeRelativePath($path)) !== []) {
            $errors[] = 'target.paths must contain unique, traversal-safe relative paths.';
        }

        $behaviors = $input['behaviors'] ?? null;
        if (! is_array($behaviors) || ! ContractRules::isList($behaviors) || $behaviors === []) {
            $errors[] = 'behaviors must be a non-empty ordered list.';
        } else {
            $ids = [];
            foreach ($behaviors as $index => $behavior) {
                $prefix = "behaviors.$index";
                if (! is_array($behavior) || ! ContractRules::validId($behavior['id'] ?? null)) {
                    $errors[] = "$prefix.id must be an unambiguous identifier.";

                    continue;
                }
                $ids[] = $behavior['id'];
                if (($behavior['order'] ?? null) !== $index + 1) {
                    $errors[] = "$prefix.order must be ".($index + 1).'.';
                }
                if (! is_string($behavior['title'] ?? null) || trim($behavior['title']) === '') {
                    $errors[] = "$prefix.title must be a non-empty string.";
                }
                foreach (['given', 'when', 'then'] as $part) {
                    $steps = $behavior[$part] ?? null;
                    if (! is_array($steps) || ! ContractRules::isList($steps) || $steps === [] || array_filter($steps, fn (mixed $step): bool => ! is_string($step) || trim($step) === '') !== []) {
                        $errors[] = "$prefix.$part must be a non-empty list of steps.";
                    }
                }
            }
            if (ContractRules::hasDuplicateStrings($ids)) {
                $errors[] = 'behavior ids must be unique.';
            }
        }

        if ($errors !== []) {
            throw new ContractValidationException($errors);
        }

        /** @var array{kind: 'git-behavior', path: string} $source */
        /** @var array{root: string, paths: list<string>} $target */
        /** @var list<array{id: string, order: int, title: string, given: list<string>, when: list<string>, then: list<string>}> $behaviors */
        $id = ContractRules::string($input['id']);
        $title = trim(ContractRules::string($input['title']));

        return new self($id, $title, $source, $target, $behaviors);
    }

    /** @return list<string> */
    public function behaviorIds(): array
    {
        return array_column($this->behaviors, 'id');
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['schema_version' => self::SCHEMA_VERSION, 'id' => $this->id, 'title' => $this->title, 'source' => $this->source, 'target' => $this->target, 'behaviors' => $this->behaviors];
    }
}
