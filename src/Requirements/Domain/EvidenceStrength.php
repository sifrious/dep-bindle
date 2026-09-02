<?php

declare(strict_types=1);

namespace Maryeperry\Bindle\Requirements\Domain;

/**
 * How much a source is allowed to be trusted when it disagrees with another.
 *
 * The order is deliberate and is the whole point of the type: a lockfile states
 * what a machine actually resolved, a manifest states what a human declared, and
 * a README states what a human once wrote down. Conflicting evidence is never
 * silently collapsed, so consumers need a stated ranking to explain a choice
 * rather than a rule buried in a detector.
 */
enum EvidenceStrength: string
{
    /** Resolved, machine-written versions: composer.lock, uv.lock, pnpm-lock.yaml. */
    case Lockfile = 'lockfile';

    /** Declared constraints: composer.json, package.json, pyproject.toml, go.mod. */
    case Manifest = 'manifest';

    /** Explicit pins: .tool-versions, .nvmrc, mise.toml. */
    case VersionFile = 'version_file';

    /** Environment declarations: Dockerfile, compose, devcontainer, .env.example. */
    case Config = 'config';

    /** Executable-but-incidental: CI workflows, Makefile, justfile. */
    case Automation = 'automation';

    /** Prose: README and setup docs. Lowest trust by design. */
    case Documentation = 'documentation';

    /**
     * Higher wins. Values are spaced so tiers can be inserted later without
     * renumbering anything already persisted.
     */
    public function rank(): int
    {
        return match ($this) {
            self::Lockfile => 60,
            self::Manifest => 50,
            self::VersionFile => 40,
            self::Config => 30,
            self::Automation => 20,
            self::Documentation => 10,
        };
    }

    public function outranks(self $other): bool
    {
        return $this->rank() > $other->rank();
    }

    /**
     * Prose is evidence, never an instruction. MME-2064 fences this: README
     * commands must not become executable actions on their own authority.
     */
    public function isAuthoritative(): bool
    {
        return $this !== self::Documentation;
    }

    public function label(): string
    {
        return match ($this) {
            self::Lockfile => 'lockfile',
            self::Manifest => 'manifest',
            self::VersionFile => 'version file',
            self::Config => 'config',
            self::Automation => 'automation',
            self::Documentation => 'documentation',
        };
    }
}
