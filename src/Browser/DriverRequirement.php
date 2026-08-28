<?php

declare(strict_types=1);

namespace Maryeperry\Bindle\Browser;

/**
 * One precondition a driver needs, and how to satisfy it. `$action` names a
 * panel-runnable Artisan command; when it is null the fix is manual and only
 * `$command` is shown for the developer to copy.
 */
final readonly class DriverRequirement
{
    public function __construct(
        public string $key,
        public string $label,
        public bool $satisfied,
        public string $consequence,
        public string $command,
        public ?string $action = null,
        public ?string $detail = null,
    ) {}

    public function isFixableFromPanel(): bool
    {
        return ! $this->satisfied && $this->action !== null;
    }
}
