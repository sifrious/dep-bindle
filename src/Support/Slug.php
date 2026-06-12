<?php

declare(strict_types=1);

namespace Maryeperry\Bindle\Support;

final class Slug
{
    public static function of(string $value): string
    {
        $value = preg_replace('/[^A-Za-z0-9]+/', '-', $value) ?? $value;
        $value = trim($value, '-');
        $value = strtolower($value);

        return $value === '' ? 'untitled' : $value;
    }

    public static function forRoute(?string $name, string $uri, string $method): string
    {
        $base = $name !== null && $name !== ''
            ? $name
            : $method.'-'.$uri;

        return self::of($base);
    }

    public static function forComponent(string $kind, string $name): string
    {
        return self::of($kind.'-'.$name);
    }
}
