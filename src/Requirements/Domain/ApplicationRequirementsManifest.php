<?php

declare(strict_types=1);

namespace Maryeperry\Bindle\Requirements\Domain;

use DateTimeImmutable;
use DateTimeInterface;
use Maryeperry\Bindle\Exceptions\MalformedManifestException;

/**
 * What one scanned codebase appears to require from a host, with the evidence
 * for every claim.
 *
 * Persistence-neutral by design. The workspace is referenced by an opaque
 * identifier and an optional revision string, and nothing about how that
 * identifier is stored or resolved appears here — MME-2064 assigns canonical
 * workspace identity to Stacks, and MME-2067 is where a scan gets bound to it.
 * Reading this manifest requires no installer and implies no execution: it says
 * what is needed and why, never what to run.
 */
final readonly class ApplicationRequirementsManifest
{
    /**
     * Wire-format identity. Emitted on every payload and checked on the way
     * back in, so a consumer built against a later shape fails loudly instead of
     * silently misreading fields.
     */
    public const string SCHEMA_VERSION = 'bindle.application-requirements.v1';

    /**
     * @param  string  $workspaceId  Opaque workspace reference; meaningless to this package.
     * @param  string|null  $revision  Frozen revision the scan read, when known.
     * @param  list<Requirement>  $requirements
     */
    public function __construct(
        public string $workspaceId,
        public DateTimeImmutable $generatedAt,
        public array $requirements = [],
        public ?string $revision = null,
    ) {}

    /**
     * @return list<Requirement>
     */
    public function ofKind(RequirementKind $kind): array
    {
        return array_values(array_filter(
            $this->requirements,
            static fn (Requirement $requirement): bool => $requirement->kind === $kind,
        ));
    }

    /**
     * Every requirement whose sources disagree. The reconciliation step in
     * MME-2068 needs these separable from clean ones, because a contested
     * requirement cannot produce a confident gap result.
     *
     * @return list<Requirement>
     */
    public function contested(): array
    {
        return array_values(array_filter(
            $this->requirements,
            static fn (Requirement $requirement): bool => $requirement->isContested(),
        ));
    }

    public function findByName(string $name): ?Requirement
    {
        foreach ($this->requirements as $requirement) {
            if ($requirement->name === $name) {
                return $requirement;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'schemaVersion' => self::SCHEMA_VERSION,
            'workspaceId' => $this->workspaceId,
            'revision' => $this->revision,
            'generatedAt' => $this->generatedAt->format(DateTimeInterface::ATOM),
            'requirements' => array_map(
                static fn (Requirement $requirement): array => $requirement->toArray(),
                $this->requirements,
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $schema = Wire::string($data, 'schemaVersion');

        if ($schema !== self::SCHEMA_VERSION) {
            throw MalformedManifestException::unsupportedSchema($schema, self::SCHEMA_VERSION);
        }

        $generatedAt = DateTimeImmutable::createFromFormat(
            DateTimeInterface::ATOM,
            Wire::string($data, 'generatedAt'),
        );

        if ($generatedAt === false) {
            throw MalformedManifestException::expected('generatedAt', 'an ISO-8601 timestamp');
        }

        return new self(
            workspaceId: Wire::string($data, 'workspaceId'),
            generatedAt: $generatedAt,
            requirements: array_map(
                static fn (array $row): Requirement => Requirement::fromArray($row),
                Wire::rows($data, 'requirements'),
            ),
            revision: Wire::nullableString($data, 'revision'),
        );
    }

    public function toJson(int $flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES): string
    {
        return json_encode($this->toArray(), $flags | JSON_THROW_ON_ERROR);
    }

    public static function fromJson(string $json): self
    {
        $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($decoded)) {
            throw MalformedManifestException::expected('manifest', 'a JSON object');
        }

        /** @var array<string, mixed> $decoded */
        return self::fromArray($decoded);
    }
}
