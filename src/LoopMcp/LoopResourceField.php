<?php

declare(strict_types=1);

namespace Maryeperry\Bindle\LoopMcp;

use InvalidArgumentException;
use JsonSerializable;
use Sifrious\ReferenceContract\CrossPackageReference;

final readonly class LoopResourceField implements JsonSerializable
{
    private const array KINDS = ['fact', 'deterministic-derivation', 'ai-interpretation'];

    private const array AVAILABILITIES = ['known', 'unknown', 'redacted'];

    /**
     * @param  list<CrossPackageReference>  $basis
     */
    private function __construct(
        public string $kind,
        public string $name,
        public string $availability,
        public mixed $value = null,
        public array $basis = [],
        public ?string $method = null,
        public ?CrossPackageReference $producer = null,
    ) {
        if (! in_array($kind, self::KINDS, true)
            || preg_match('/^[a-z][a-z0-9._-]*$/', $name) !== 1
            || ! in_array($availability, self::AVAILABILITIES, true)) {
            throw new InvalidArgumentException('Loop resource fields require a stable kind, name, and availability.');
        }

        if ($availability !== 'known' && ($value !== null || $basis !== [] || $producer instanceof CrossPackageReference)) {
            throw new InvalidArgumentException('Unknown and redacted fields cannot carry values, references, or producers.');
        }

        if ($kind === 'deterministic-derivation' && ($method === null || trim($method) === '')) {
            throw new InvalidArgumentException('Deterministic derivations must name their method.');
        }

        if ($kind === 'ai-interpretation' && $availability === 'known' && ! $producer instanceof CrossPackageReference) {
            throw new InvalidArgumentException('Known AI interpretations must identify their producer.');
        }

        if ($kind === 'fact' && ($method !== null || $producer instanceof CrossPackageReference)) {
            throw new InvalidArgumentException('Facts cannot masquerade as derivations or interpretations.');
        }

        self::normalize($value);
    }

    /**
     * @param  list<CrossPackageReference>  $sources
     */
    public static function fact(string $name, mixed $value, array $sources = []): self
    {
        return new self('fact', $name, 'known', $value, $sources);
    }

    public static function unknownFact(string $name): self
    {
        return new self('fact', $name, 'unknown');
    }

    public static function redactedFact(string $name): self
    {
        return new self('fact', $name, 'redacted');
    }

    /**
     * @param  list<CrossPackageReference>  $basis
     */
    public static function derivation(string $name, mixed $value, string $method, array $basis): self
    {
        return new self('deterministic-derivation', $name, 'known', $value, $basis, $method);
    }

    public static function unknownDerivation(string $name, string $method): self
    {
        return new self('deterministic-derivation', $name, 'unknown', method: $method);
    }

    /**
     * @param  list<CrossPackageReference>  $basis
     */
    public static function interpretation(string $name, mixed $value, CrossPackageReference $producer, array $basis): self
    {
        return new self('ai-interpretation', $name, 'known', $value, $basis, producer: $producer);
    }

    public static function redactedInterpretation(string $name): self
    {
        return new self('ai-interpretation', $name, 'redacted');
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'availability' => $this->availability,
            'value' => $this->availability === 'known' ? self::normalize($this->value) : null,
            'basis' => array_map(
                static fn (CrossPackageReference $reference): array => $reference->toArray(),
                $this->basis,
            ),
            'method' => $this->method,
            'producer' => $this->producer?->toArray(),
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    private static function normalize(mixed $value): mixed
    {
        if ($value instanceof CrossPackageReference) {
            return $value->toArray();
        }

        if (is_array($value)) {
            $normalized = [];
            foreach ($value as $key => $item) {
                if (is_string($key) && preg_match('/(?:secret|token|password|credential|private[_-]?key)/i', $key) === 1) {
                    throw new InvalidArgumentException("Sensitive field [{$key}] must be redacted before Loop MCP serialization.");
                }
                $normalized[$key] = self::normalize($item);
            }

            return $normalized;
        }

        if ($value === null || is_scalar($value)) {
            return $value;
        }

        throw new InvalidArgumentException('Loop resource values must contain JSON scalars, arrays, or cross-package references.');
    }
}
