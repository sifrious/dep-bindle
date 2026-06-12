<?php

declare(strict_types=1);

namespace Maryeperry\Bindle\Routes;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use ReflectionClass;
use ReflectionException;

final class RouteEnumerator
{
    public function __construct(
        private readonly Router $router,
        private readonly Repository $config,
    ) {}

    /**
     * @return list<ResolvedRoute>
     */
    public function enumerate(): array
    {
        $include = (array) $this->config->get('bindle.routes.include', ['*']);
        $exclude = (array) $this->config->get('bindle.routes.exclude', []);
        $methods = array_map('strtoupper', (array) $this->config->get('bindle.routes.methods', ['GET']));

        $resolved = [];

        foreach ($this->router->getRoutes() as $route) {
            $routeMethods = array_diff($route->methods(), ['HEAD']);
            if (array_intersect($methods, $routeMethods) === []) {
                continue;
            }

            $name = $route->getName();
            $uri = '/'.ltrim($route->uri(), '/');

            if (! $this->matchesAny($name ?? $uri, $include)) {
                continue;
            }
            if ($this->matchesAny($name ?? $uri, $exclude)) {
                continue;
            }

            $controller = $this->controllerClass($route);
            $action = $this->controllerMethod($route);
            $view = $this->guessView($route, $controller, $action);
            $framework = $this->guessFramework($controller, $action);

            $resolved[] = new ResolvedRoute(
                name: $name,
                uri: $uri,
                method: array_values(array_intersect($methods, $routeMethods))[0] ?? 'GET',
                controller: $controller,
                action: $action,
                view: $view,
                framework: $framework,
                parameterNames: $route->parameterNames(),
                middleware: $route->gatherMiddleware(),
            );
        }

        return $resolved;
    }

    private function matchesAny(string $value, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if ($pattern === '*' || fnmatch($pattern, $value)) {
                return true;
            }
        }

        return false;
    }

    private function controllerClass(Route $route): ?string
    {
        $action = $route->getAction();
        if (is_string($action['controller'] ?? null)) {
            return explode('@', $action['controller'])[0];
        }
        if (is_string($action['uses'] ?? null) && str_contains($action['uses'], '@')) {
            return explode('@', $action['uses'])[0];
        }

        return null;
    }

    private function controllerMethod(Route $route): ?string
    {
        $action = $route->getAction();
        if (is_string($action['controller'] ?? null) && str_contains($action['controller'], '@')) {
            return explode('@', $action['controller'])[1];
        }
        if (is_string($action['uses'] ?? null) && str_contains($action['uses'], '@')) {
            return explode('@', $action['uses'])[1];
        }

        return null;
    }

    private function guessView(Route $route, ?string $controller, ?string $action): ?string
    {
        // Best-effort: read the controller method source for a `view('...')` or
        // `Inertia::render('...')` call. This is heuristic but avoids running
        // the actual handler.
        if ($controller === null || $action === null) {
            return null;
        }

        try {
            $rc = new ReflectionClass($controller);
            if (! $rc->hasMethod($action)) {
                return null;
            }
            $rm = $rc->getMethod($action);
            $file = $rm->getFileName();
            if ($file === false) {
                return null;
            }

            $start = $rm->getStartLine() ?: 0;
            $end = $rm->getEndLine() ?: 0;
            if ($start === 0 || $end === 0) {
                return null;
            }

            $source = implode("\n", array_slice(file($file) ?: [], $start - 1, $end - $start + 1));

            if (preg_match("/view\\(\\s*['\"]([^'\"]+)['\"]/", $source, $m)) {
                return $m[1];
            }
            if (preg_match("/Inertia::render\\(\\s*['\"]([^'\"]+)['\"]/", $source, $m)) {
                return $m[1];
            }
        } catch (ReflectionException) {
            return null;
        }

        return null;
    }

    private function guessFramework(?string $controller, ?string $action): string
    {
        if ($controller === null || $action === null) {
            return 'unknown';
        }

        try {
            $rc = new ReflectionClass($controller);
            if (! $rc->hasMethod($action)) {
                return 'unknown';
            }
            $rm = $rc->getMethod($action);
            $file = $rm->getFileName();
            if ($file === false) {
                return 'unknown';
            }

            $start = $rm->getStartLine() ?: 0;
            $end = $rm->getEndLine() ?: 0;
            $source = implode("\n", array_slice(file($file) ?: [], $start - 1, $end - $start + 1));

            if (str_contains($source, 'Inertia::render') || str_contains($source, '->inertia(')) {
                return 'inertia';
            }
            if (preg_match('/view\\(/', $source)) {
                return 'blade';
            }
        } catch (ReflectionException) {
            return 'unknown';
        }

        return 'unknown';
    }
}
