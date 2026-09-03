<?php

declare(strict_types=1);

namespace Maryeperry\Bindle\Requirements\Domain;

/**
 * What sort of thing a requirement is, so a consumer can reconcile it against
 * the right kind of host observation without parsing its name.
 */
enum RequirementKind: string
{
    /** A language runtime: php, node, python. */
    case Runtime = 'runtime';

    /** A dependency manager: composer, pnpm, uv. */
    case PackageManager = 'package_manager';

    /** An executable on PATH: git, gh, make. */
    case SystemTool = 'system_tool';

    /** A running service the app talks to: postgresql, redis. */
    case Service = 'service';

    /** A named environment variable the app reads. */
    case EnvironmentVariable = 'environment_variable';

    /** A writable path, permission, or similar filesystem affordance. */
    case FilesystemCapability = 'filesystem_capability';

    /** Work that must have been performed: dependencies installed, migrations run. */
    case BuildStep = 'build_step';

    public function label(): string
    {
        return match ($this) {
            self::Runtime => 'runtime',
            self::PackageManager => 'package manager',
            self::SystemTool => 'system tool',
            self::Service => 'service',
            self::EnvironmentVariable => 'environment variable',
            self::FilesystemCapability => 'filesystem capability',
            self::BuildStep => 'build step',
        };
    }

    /**
     * Whether a version constraint is meaningful for this kind. An environment
     * variable has no version; a runtime almost always does.
     */
    public function carriesVersion(): bool
    {
        return match ($this) {
            self::Runtime, self::PackageManager, self::SystemTool, self::Service => true,
            self::EnvironmentVariable, self::FilesystemCapability, self::BuildStep => false,
        };
    }
}
