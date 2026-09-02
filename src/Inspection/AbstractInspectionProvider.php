<?php

declare(strict_types=1);

namespace Maryeperry\Bindle\Inspection;

use Maryeperry\Bindle\Inspection\Contracts\InspectionProvider;

abstract class AbstractInspectionProvider implements InspectionProvider
{
    final protected function absolutePath(string $rootPath, string $relativePath): ?string
    {
        $root = realpath($rootPath);
        $path = realpath(rtrim($rootPath, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.ltrim($relativePath, DIRECTORY_SEPARATOR));

        if ($root === false || $path === false || ($path !== $root && ! str_starts_with($path, $root.DIRECTORY_SEPARATOR))) {
            return null;
        }

        return $path;
    }

    final protected function relativePath(string $rootPath, string $absolutePath): string
    {
        return ltrim(substr($absolutePath, strlen((string) realpath($rootPath))), DIRECTORY_SEPARATOR);
    }
}
