<?php

declare(strict_types=1);

namespace Maryeperry\Bindle\Requirements\Domain;

use Maryeperry\Bindle\Exceptions\MalformedManifestException;

/**
 * One thing the scanned application appears to need from its host.
 *
 * A requirement cannot exist without evidence. MME-2064 states the invariant
 * directly — every inferred requirement carries source evidence and confidence —
 * so the constructor refuses an empty evidence list rather than letting a
 * requirement that nothing can explain reach a consumer.
 */
final readonly class Requirement
{
    /**
     * @param  string  $name  Ecosystem-neutral identity, e.g. "php", "pnpm", "postgresql".
     * @param  list<Evidence>  $evidence  Why this is believed; must not be empty.
     * @param  list<SetupHint>  $setupHints  Typed intent, never commands.
     * @param  list<RequirementConflict>  $conflicts  Disagreements left unresolved on purpose.
     */
    public function __construct(
        public string $name,
        public RequirementKind $kind,
        public RequirementNecessity $necessity,
        public Confidence $confidence,
        public array $evidence,
        public ?VersionConstraint $version = null,
        public array $setupHints = [],
        public array $conflicts = [],
        public ?string $description = null,
    ) {
        if ($this->evidence === []) {
            throw MalformedManifestException::requirementNeedsEvidence($this->name);
        }
    }

    /**
     * The highest-ranked observation behind this requirement. Used to explain a
     * requirement's provenance in one line without replaying every source.
     */
    public function strongestEvidence(): Evidence
    {
        $best = $this->evidence[0];

        foreach ($this->evidence as $candidate) {
            if ($candidate->strength->outranks($best->strength)) {
                $best = $candidate;
            }
        }

        return $best;
    }

    /**
     * Whether every source behind this requirement is prose. Such a requirement
     * is real enough to report but must not drive an automated action.
     */
    public function restsOnlyOnDocumentation(): bool
    {
        foreach ($this->evidence as $candidate) {
            if ($candidate->strength->isAuthoritative()) {
                return false;
            }
        }

        return true;
    }

    public function isContested(): bool
    {
        return $this->conflicts !== [];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'kind' => $this->kind->value,
            'necessity' => $this->necessity->value,
            'confidence' => $this->confidence->value,
            'version' => $this->version?->toArray(),
            'evidence' => array_map(
                static fn (Evidence $evidence): array => $evidence->toArray(),
                $this->evidence,
            ),
            'setupHints' => array_map(
                static fn (SetupHint $hint): array => $hint->toArray(),
                $this->setupHints,
            ),
            'conflicts' => array_map(
                static fn (RequirementConflict $conflict): array => $conflict->toArray(),
                $this->conflicts,
            ),
            'description' => $this->description,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $kind = Wire::string($data, 'kind');
        $necessity = Wire::string($data, 'necessity');
        $confidence = Wire::string($data, 'confidence');
        $version = $data['version'] ?? null;

        if ($version !== null && ! is_array($version)) {
            throw MalformedManifestException::expected('version', 'an object or null');
        }

        /** @var array<string, mixed>|null $version */
        return new self(
            name: Wire::string($data, 'name'),
            kind: RequirementKind::tryFrom($kind)
                ?? throw MalformedManifestException::unknownValue('kind', $kind, 'requirement kind'),
            necessity: RequirementNecessity::tryFrom($necessity)
                ?? throw MalformedManifestException::unknownValue('necessity', $necessity, 'necessity'),
            confidence: Confidence::tryFrom($confidence)
                ?? throw MalformedManifestException::unknownValue('confidence', $confidence, 'confidence'),
            evidence: array_map(
                static fn (array $row): Evidence => Evidence::fromArray($row),
                Wire::rows($data, 'evidence'),
            ),
            version: $version === null ? null : VersionConstraint::fromArray($version),
            setupHints: array_map(
                static fn (array $row): SetupHint => SetupHint::fromArray($row),
                Wire::rows($data, 'setupHints'),
            ),
            conflicts: array_map(
                static fn (array $row): RequirementConflict => RequirementConflict::fromArray($row),
                Wire::rows($data, 'conflicts'),
            ),
            description: Wire::nullableString($data, 'description'),
        );
    }
}
