<?php

declare(strict_types=1);

namespace Maryeperry\Bindle\Composition\Evidence;

use InvalidArgumentException;
use Maryeperry\Bindle\Browser\DriverKind;

final readonly class RenderEvidence
{
    /**
     * @param  array<string, string>  $screenshots
     * @param  list<array<string, mixed>>  $testResults
     */
    public function __construct(
        public string $compositionId,
        public string $route,
        public DriverKind $driver,
        public array $screenshots,
        public array $testResults,
    ) {
        if ($compositionId === '' || $route === '') {
            throw new InvalidArgumentException('Composition ID and route are required.');
        }

        if (! $driver->producesRealScreenshots()) {
            throw new InvalidArgumentException('Placeholder screenshots are not render evidence.');
        }

        foreach (['desktop', 'mobile'] as $viewport) {
            $path = $screenshots[$viewport] ?? null;
            if (! is_string($path) || ! is_file($path) || filesize($path) < 100) {
                throw new InvalidArgumentException("Real {$viewport} screenshot evidence is required.");
            }
        }

        if ($testResults === []) {
            throw new InvalidArgumentException('At least one target test result is required.');
        }
        foreach ($testResults as $result) {
            if (! isset($result['behavior_id'], $result['status'])) {
                throw new InvalidArgumentException('Test results must link a behavior ID and status.');
            }
        }
    }

    /** @return array{composition_id: string, route: string, driver: string, screenshots: array<string, string>, test_results: list<array<string, mixed>>} */
    public function toArray(): array
    {
        return [
            'composition_id' => $this->compositionId,
            'route' => $this->route,
            'driver' => $this->driver->value,
            'screenshots' => $this->screenshots,
            'test_results' => $this->testResults,
        ];
    }
}
