<?php

declare(strict_types=1);

namespace Maryeperry\Bindle\Requirements\Domain;

use Maryeperry\Bindle\Exceptions\MalformedManifestException;

/**
 * A disagreement between sources about one requirement, recorded rather than resolved.
 *
 * Both sides are kept. When composer.json says `^8.2` and .tool-versions pins
 * `8.3.6`, the manifest reports the conflict and leaves the requirement's own
 * constraint set to whichever the detector ranked highest — so a reader can see
 * the disagreement and disagree with the ranking.
 */
final readonly class RequirementConflict
{
    /**
     * @param  list<Evidence>  $evidence  The disagreeing observations, at least two.
     */
    public function __construct(
        public ConflictKind $kind,
        public array $evidence,
        public ?string $note = null,
    ) {}

    /**
     * The evidence that would win on strength alone. Null when nothing outranks
     * everything else, which is itself the useful answer: the tie is real.
     */
    public function strongest(): ?Evidence
    {
        $best = null;
        $tied = false;

        foreach ($this->evidence as $candidate) {
            if ($best === null || $candidate->strength->outranks($best->strength)) {
                $best = $candidate;
                $tied = false;

                continue;
            }

            if ($candidate->strength === $best->strength) {
                $tied = true;
            }
        }

        return $tied ? null : $best;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'kind' => $this->kind->value,
            'evidence' => array_map(
                static fn (Evidence $evidence): array => $evidence->toArray(),
                $this->evidence,
            ),
            'note' => $this->note,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $kind = Wire::string($data, 'kind');

        return new self(
            kind: ConflictKind::tryFrom($kind)
                ?? throw MalformedManifestException::unknownValue('kind', $kind, 'conflict kind'),
            evidence: array_map(
                static fn (array $row): Evidence => Evidence::fromArray($row),
                Wire::rows($data, 'evidence'),
            ),
            note: Wire::nullableString($data, 'note'),
        );
    }
}
