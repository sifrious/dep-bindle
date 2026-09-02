<?php

declare(strict_types=1);

namespace Maryeperry\Bindle\Requirements\Domain;

/**
 * Where in the codebase a piece of evidence was read.
 *
 * Paths are workspace-relative so a manifest stays portable across checkouts;
 * resolving one to an absolute path is the caller's business, which is what
 * keeps this contract free of any local-registry detail.
 */
final readonly class EvidenceLocator
{
    /**
     * @param  string  $relativePath  Workspace-relative path, e.g. "composer.json".
     * @param  int|null  $line  1-indexed line, for line-oriented sources such as prose.
     * @param  string|null  $pointer  Structural pointer into the parsed file, e.g. "/require/php".
     */
    public function __construct(
        public string $relativePath,
        public ?int $line = null,
        public ?string $pointer = null,
    ) {}

    public function describe(): string
    {
        if ($this->pointer !== null) {
            return $this->relativePath.$this->pointer;
        }

        if ($this->line !== null) {
            return $this->relativePath.':'.$this->line;
        }

        return $this->relativePath;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'relativePath' => $this->relativePath,
            'line' => $this->line,
            'pointer' => $this->pointer,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            relativePath: Wire::string($data, 'relativePath'),
            line: Wire::nullableInt($data, 'line'),
            pointer: Wire::nullableString($data, 'pointer'),
        );
    }
}
