<?php

declare(strict_types=1);

namespace Maryeperry\Bindle\Exceptions;

use InvalidArgumentException;

/**
 * A serialized application-requirements manifest could not be read back.
 *
 * Consumers outside this package hold the wire format, so a bad payload is an
 * argument error at the boundary rather than a corrupt-state error inside it.
 */
final class MalformedManifestException extends InvalidArgumentException
{
    public static function expected(string $key, string $type): self
    {
        return new self(sprintf('Manifest key "%s" must be %s.', $key, $type));
    }

    public static function unknownValue(string $key, string $value, string $type): self
    {
        return new self(sprintf('Manifest key "%s" has unknown %s value "%s".', $key, $type, $value));
    }

    public static function unsupportedSchema(string $found, string $supported): self
    {
        return new self(sprintf(
            'Unsupported manifest schema "%s"; this build reads "%s".',
            $found,
            $supported,
        ));
    }

    public static function requirementNeedsEvidence(string $name): self
    {
        return new self(sprintf('Requirement "%s" must carry at least one evidence record.', $name));
    }
}
