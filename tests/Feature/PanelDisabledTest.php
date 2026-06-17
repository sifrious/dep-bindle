<?php

declare(strict_types=1);

// Uses the default Tests\TestCase (app.env = "testing", panel flag default
// off), so the service provider never registers the panel routes.

it('does not register panel routes outside local', function (): void {
    $this->get('/_bindle')->assertNotFound();
    $this->post('/_bindle/scan')->assertNotFound();
});
