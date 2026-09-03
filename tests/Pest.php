<?php

declare(strict_types=1);
use Maryeperry\Bindle\Requirements\Domain\ApplicationRequirementsManifest;
use Tests\PanelTestCase;
use Tests\TestCase;

uses(TestCase::class)->in('Feature', 'Unit');

// Panel routes are registered at boot only in a "local" environment, so these
// tests boot with that environment + the panel flag enabled.
uses(PanelTestCase::class)->in('Panel');

/**
 * Loads a recorded application-requirements manifest from tests/Fixtures.
 *
 * The fixtures are the worked examples the contract has to be able to express,
 * so they are shared rather than rebuilt per test file.
 */
function manifestFixture(string $name): ApplicationRequirementsManifest
{
    $path = __DIR__.'/Fixtures/requirements/'.$name.'.json';

    return ApplicationRequirementsManifest::fromJson((string) file_get_contents($path));
}
