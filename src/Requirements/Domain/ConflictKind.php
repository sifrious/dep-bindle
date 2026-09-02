<?php

declare(strict_types=1);

namespace Maryeperry\Bindle\Requirements\Domain;

/**
 * The shape of a disagreement between sources. Recorded, never resolved:
 * MME-2064 requires that conflicting evidence stay explicit so a human can see
 * why a requirement is uncertain instead of inheriting a detector's guess.
 */
enum ConflictKind: string
{
    /** Sources agree the thing is needed but disagree on the version. */
    case VersionDisagreement = 'version_disagreement';

    /** Sources disagree on whether it is required, optional, or dev-only. */
    case NecessityDisagreement = 'necessity_disagreement';

    /** Evidence is too vague to pin down; e.g. prose naming no version at all. */
    case PresenceAmbiguity = 'presence_ambiguity';

    public function label(): string
    {
        return match ($this) {
            self::VersionDisagreement => 'version disagreement',
            self::NecessityDisagreement => 'necessity disagreement',
            self::PresenceAmbiguity => 'presence ambiguity',
        };
    }
}
