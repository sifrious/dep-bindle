<?php

declare(strict_types=1);

namespace Maryeperry\Bindle\Requirements\Domain;

/**
 * A version requirement exactly as the source stated it, with an optional
 * normalized form.
 *
 * The raw string is authoritative and always preserved: "^8.3", ">=20" and
 * "3.12.*" come from three different ecosystems and normalizing them is a
 * detector's judgment, not a fact about the codebase. A consumer that wants to
 * compare versions reads `normalized` when a detector could produce one
 * deterministically, and otherwise knows it must not guess.
 */
final readonly class VersionConstraint
{
    public function __construct(
        public string $raw,
        public ?string $normalized = null,
    ) {}

    public function isNormalized(): bool
    {
        return $this->normalized !== null;
    }

    public function equals(self $other): bool
    {
        return $this->raw === $other->raw;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'raw' => $this->raw,
            'normalized' => $this->normalized,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            raw: Wire::string($data, 'raw'),
            normalized: Wire::nullableString($data, 'normalized'),
        );
    }
}
