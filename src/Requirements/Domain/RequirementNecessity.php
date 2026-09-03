<?php

declare(strict_types=1);

namespace Maryeperry\Bindle\Requirements\Domain;

/**
 * Whether the application actually needs this, and in which context. Kept
 * separate from confidence: a dev-only requirement can be certain, and a
 * required one can be a guess.
 */
enum RequirementNecessity: string
{
    case Required = 'required';

    case Optional = 'optional';

    case DevOnly = 'dev_only';

    case TestOnly = 'test_only';

    public function label(): string
    {
        return match ($this) {
            self::Required => 'required',
            self::Optional => 'optional',
            self::DevOnly => 'dev only',
            self::TestOnly => 'test only',
        };
    }

    /**
     * Whether absence should read as a gap rather than a note. Optional is the
     * only kind whose absence is not a problem; dev and test requirements are
     * genuinely needed in the context this package runs in.
     */
    public function absenceIsAGap(): bool
    {
        return $this !== self::Optional;
    }
}
