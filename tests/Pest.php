<?php

declare(strict_types=1);
use Tests\PanelTestCase;
use Tests\TestCase;

uses(TestCase::class)->in('Feature', 'Unit');

// Panel routes are registered at boot only in a "local" environment, so these
// tests boot with that environment + the panel flag enabled.
uses(PanelTestCase::class)->in('Panel');
