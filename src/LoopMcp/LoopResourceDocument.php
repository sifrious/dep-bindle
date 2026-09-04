<?php

declare(strict_types=1);

namespace Maryeperry\Bindle\LoopMcp;

use InvalidArgumentException;
use JsonSerializable;
use Sifrious\AuthorizationContract\AuthorizationContext;
use Sifrious\AuthorizationContract\AuthorizationDecision;
use Sifrious\AuthorizationContract\DisclosureMode;
use Sifrious\ReferenceContract\CrossPackageReference;

final readonly class LoopResourceDocument implements JsonSerializable
{
    public const string CONTRACT = 'sifrious.loop-mcp-resource';

    public const int CONTRACT_VERSION = 1;

    /**
     * @param  list<LoopResourceField>  $facts
     * @param  list<LoopResourceField>  $derivations
     * @param  list<LoopResourceField>  $interpretations
     */
    private function __construct(
        public LoopResourceType $resourceType,
        public string $uri,
        public string $resolution,
        public ?CrossPackageReference $reference,
        public AuthorizationContext $authorization,
        public AuthorizationDecision $decision,
        public array $facts = [],
        public array $derivations = [],
        public array $interpretations = [],
    ) {
        $this->assertFields($facts, 'fact');
        $this->assertFields($derivations, 'deterministic-derivation');
        $this->assertFields($interpretations, 'ai-interpretation');
    }

    /**
     * @param  list<LoopResourceField>  $facts
     * @param  list<LoopResourceField>  $derivations
     * @param  list<LoopResourceField>  $interpretations
     */
    public static function available(
        LoopResourceType $type,
        CrossPackageReference $reference,
        AuthorizationContext $authorization,
        AuthorizationDecision $decision,
        array $facts = [],
        array $derivations = [],
        array $interpretations = [],
    ): self {
        if (! $decision->permitted) {
            throw new InvalidArgumentException('Available Loop resources require a permitting authorization decision.');
        }

        return new self(
            $type,
            self::uriFor($type, $reference),
            'available',
            $reference,
            $authorization,
            $decision,
            $facts,
            $derivations,
            $interpretations,
        );
    }

    public static function unknown(
        LoopResourceType $type,
        CrossPackageReference $reference,
        AuthorizationContext $authorization,
        AuthorizationDecision $decision,
    ): self {
        if (! $decision->permitted) {
            throw new InvalidArgumentException('Unknown Loop resources require a permitting authorization decision.');
        }

        return new self(
            $type,
            self::uriFor($type, $reference),
            'unknown',
            $reference,
            $authorization,
            $decision,
        );
    }

    public static function denied(
        LoopResourceType $type,
        CrossPackageReference $requestedReference,
        AuthorizationContext $authorization,
        AuthorizationDecision $decision,
    ): self {
        if ($decision->permitted) {
            throw new InvalidArgumentException('Denied Loop resources require a denying authorization decision.');
        }

        $resolution = $decision->disclosure === DisclosureMode::ConcealAsMissing
            ? 'missing'
            : 'forbidden';

        return new self(
            $type,
            self::uriFor($type, $requestedReference),
            $resolution,
            null,
            $authorization,
            $decision,
        );
    }

    public static function uriFor(LoopResourceType $type, CrossPackageReference $reference): string
    {
        $query = [
            'owner' => $reference->owner,
            'type' => $reference->type,
            'id' => $reference->id,
        ];

        if ($reference->objectVersion !== null) {
            $query['object_version'] = $reference->objectVersion;
        }

        return sprintf(
            'loop://resources/%s?%s',
            $type->value,
            http_build_query($query, '', '&', PHP_QUERY_RFC3986),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'contract' => self::CONTRACT,
            'contract_version' => self::CONTRACT_VERSION,
            'resource_type' => $this->resourceType->value,
            'uri' => $this->uri,
            'resolution' => $this->resolution,
            'reference' => $this->reference?->toArray(),
            'authorization' => $this->authorization->toArray(),
            'authorization_decision' => $this->decision->toArray(),
            'facts' => array_map(
                static fn (LoopResourceField $field): array => $field->toArray(),
                $this->facts,
            ),
            'deterministic_derivations' => array_map(
                static fn (LoopResourceField $field): array => $field->toArray(),
                $this->derivations,
            ),
            'ai_interpretations' => array_map(
                static fn (LoopResourceField $field): array => $field->toArray(),
                $this->interpretations,
            ),
        ];
    }

    public function toJson(): string
    {
        return json_encode(self::canonicalize($this->toArray()), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * @param  list<LoopResourceField>  $fields
     */
    private function assertFields(array $fields, string $expectedKind): void
    {
        $names = [];

        foreach ($fields as $field) {
            if (! $field instanceof LoopResourceField || $field->kind !== $expectedKind) {
                throw new InvalidArgumentException("Loop resource {$expectedKind} fields must use their matching constructor.");
            }
            if (isset($names[$field->name])) {
                throw new InvalidArgumentException("Duplicate Loop resource field [{$field->name}].");
            }
            $names[$field->name] = true;
        }
    }

    private static function canonicalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (! array_is_list($value)) {
            ksort($value, SORT_STRING);
        }

        foreach ($value as $key => $item) {
            $value[$key] = self::canonicalize($item);
        }

        return $value;
    }
}
