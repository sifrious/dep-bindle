<?php

declare(strict_types=1);

namespace Maryeperry\Bindle\Requirements\Domain;

use Maryeperry\Bindle\Exceptions\MalformedManifestException;

/**
 * One observation that made a detector believe a requirement exists.
 *
 * The excerpt is the literal text that was read, kept verbatim so a reader can
 * answer "why is this required?" without re-running the scan or trusting a
 * paraphrase.
 */
final readonly class Evidence
{
    public function __construct(
        public EvidenceStrength $strength,
        public EvidenceLocator $locator,
        public string $excerpt,
        public ?string $note = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'strength' => $this->strength->value,
            'locator' => $this->locator->toArray(),
            'excerpt' => $this->excerpt,
            'note' => $this->note,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $strength = Wire::string($data, 'strength');
        $locator = $data['locator'] ?? null;

        if (! is_array($locator)) {
            throw MalformedManifestException::expected('locator', 'an object');
        }

        /** @var array<string, mixed> $locator */
        return new self(
            strength: EvidenceStrength::tryFrom($strength)
                ?? throw MalformedManifestException::unknownValue('strength', $strength, 'evidence strength'),
            locator: EvidenceLocator::fromArray($locator),
            excerpt: Wire::string($data, 'excerpt'),
            note: Wire::nullableString($data, 'note'),
        );
    }
}
