<?php

declare(strict_types=1);

namespace Tests;

/**
 * Boots the app as a `local` environment with the admin panel enabled, so the
 * service provider actually registers the panel routes (route registration
 * happens at boot, before any test body runs).
 */
abstract class PanelTestCase extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        // $app->environment() reads the container's bound "env" string (derived
        // from APP_ENV, which phpunit pins to "testing"). Set both that binding
        // and the config so the provider registers the panel routes at boot.
        $app['env'] = 'local';
        $app['config']->set('app.env', 'local');
        $app['config']->set('bindle.panel.enabled', true);

        // The panel routes run through the `web` middleware group, which
        // encrypts cookies and therefore needs an application key.
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
    }
}
