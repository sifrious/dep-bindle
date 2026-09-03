<?php

declare(strict_types=1);

namespace Maryeperry\Bindle\Requirements\Domain;

/**
 * What would have to happen for a requirement to be satisfied, named as intent.
 *
 * There is deliberately no case for "run this command". MME-2064 fences the
 * whole capability on it: Bindle emits typed intent, and a later trusted
 * installer decides what any intent means on a given host under its own
 * approval policy. Cases mirror the operation vocabulary MME-2069 consumes.
 */
enum SetupIntent: string
{
    case InstallRuntime = 'install_runtime';

    case InstallPackageManager = 'install_package_manager';

    case InstallSystemTool = 'install_system_tool';

    case EnsureService = 'ensure_service';

    case CreateDatabase = 'create_database';

    case ConfigureEnvironment = 'configure_environment';

    case InstallProjectDependencies = 'install_project_dependencies';

    case RunMigration = 'run_migration';

    case VerifyHealth = 'verify_health';

    case RequireUserAuthentication = 'require_user_authentication';

    /**
     * Whether satisfying this intent needs a human rather than a package
     * manager. Authentication cannot be automated away, and MME-2069 has to
     * route it to a manual step rather than a retryable operation.
     */
    public function requiresHuman(): bool
    {
        return $this === self::RequireUserAuthentication;
    }
}
