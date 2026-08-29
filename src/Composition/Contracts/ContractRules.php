<?php

declare(strict_types=1);

namespace Maryeperry\Bindle\Composition\Contracts;

final class ContractRules
{
    public static function validId(mixed $value): bool
    {
        return is_string($value) && preg_match('/^[a-zA-Z0-9][a-zA-Z0-9._:-]*$/', $value) === 1;
    }

    public static function safeRelativePath(mixed $value): bool
    {
        if (! is_string($value) || $value === '' || str_contains($value, "\0") || str_contains($value, '\\')) {
            return false;
        }

        if (str_starts_with($value, '/') || preg_match('/^[a-zA-Z]:/', $value) === 1) {
            return false;
        }

        foreach (explode('/', $value) as $segment) {
            if (in_array($segment, ['', '.', '..'], true)) {
                return false;
            }
        }

        return true;
    }

    public static function safeAbsolutePath(mixed $value): bool
    {
        if (! is_string($value) || ! str_starts_with($value, '/') || str_contains($value, "\0") || str_contains($value, '\\')) {
            return false;
        }

        foreach (explode('/', substr($value, 1)) as $segment) {
            if (in_array($segment, ['', '.', '..'], true)) {
                return false;
            }
        }

        return true;
    }

    /** @param array<mixed> $value */
    public static function isList(array $value): bool
    {
        return array_is_list($value);
    }

    /** @param array<mixed> $values */
    public static function hasDuplicateStrings(array $values): bool
    {
        $seen = [];
        foreach ($values as $value) {
            if (! is_string($value)) {
                continue;
            }
            if (isset($seen[$value])) {
                return true;
            }
            $seen[$value] = true;
        }

        return false;
    }

    public static function string(mixed $value): string
    {
        if (! is_string($value)) {
            throw new \LogicException('Validated contract value was not a string.');
        }

        return $value;
    }
}
