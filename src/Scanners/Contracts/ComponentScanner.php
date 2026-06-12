<?php

declare(strict_types=1);

namespace Maryeperry\Bindle\Scanners\Contracts;

use Maryeperry\Bindle\Scanners\DiscoveredCallSite;
use Maryeperry\Bindle\Scanners\DiscoveredComponent;

interface ComponentScanner
{
    /**
     * What kind of component does this scanner produce?
     * One of: blade-anon, blade-class, livewire, alpine, inertia-page, vue, react, svelte
     */
    public function kind(): string;

    /**
     * Walk the project and yield every component definition this scanner
     * understands.
     *
     * @return iterable<DiscoveredComponent>
     */
    public function discover(): iterable;

    /**
     * Given a page (view name and/or rendered HTML), yield every usage of a
     * component of this scanner's kind.
     *
     * @return iterable<DiscoveredCallSite>
     */
    public function callSitesIn(?string $viewName, ?string $renderedHtml): iterable;
}
