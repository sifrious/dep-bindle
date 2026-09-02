<?php

declare(strict_types=1);

namespace Maryeperry\Bindle\Requirements\Domain;

use Maryeperry\Bindle\Exceptions\MalformedManifestException;

/**
 * Narrowing helpers for reading an untyped decoded-JSON payload back into the
 * manifest value objects.
 *
 * Every read states the type it demands and fails loudly when the payload does
 * not match, so a malformed manifest surfaces at the boundary instead of as a
 * type error three objects deeper.
 *
 * @internal
 */
final class Wire
{
    /**
     * @param  array<string, mixed>  $data
     */
    public static function string(array $data, string $key): string
    {
        $value = $data[$key] ?? null;

        if (! is_string($value)) {
            throw MalformedManifestException::expected($key, 'a string');
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function nullableString(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        if ($value === null) {
            return null;
        }

        if (! is_string($value)) {
            throw MalformedManifestException::expected($key, 'a string or null');
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function nullableInt(array $data, string $key): ?int
    {
        $value = $data[$key] ?? null;

        if ($value === null) {
            return null;
        }

        if (! is_int($value)) {
            throw MalformedManifestException::expected($key, 'an integer or null');
        }

        return $value;
    }

    /**
     * Reads a list of nested objects. A missing key is an empty list so that
     * optional collections do not need a placeholder in the payload.
     *
     * @param  array<string, mixed>  $data
     * @return list<array<string, mixed>>
     */
    public static function rows(array $data, string $key): array
    {
        $value = $data[$key] ?? [];

        if (! is_array($value)) {
            throw MalformedManifestException::expected($key, 'a list of objects');
        }

        $rows = [];

        foreach ($value as $row) {
            if (! is_array($row)) {
                throw MalformedManifestException::expected($key, 'a list of objects');
            }

            /** @var array<string, mixed> $row */
            $rows[] = $row;
        }

        return $rows;
    }
}
