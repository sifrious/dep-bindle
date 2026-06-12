<?php

declare(strict_types=1);

namespace Maryeperry\Bindle\Routes;

final class ResolvedRoute
{
    public function __construct(
        public readonly ?string $name,
        public readonly string $uri,
        public readonly string $method,
        public readonly ?string $controller,
        public readonly ?string $action,
        public readonly ?string $view,
        public readonly string $framework, // 'blade' | 'inertia' | 'unknown'
        public readonly array $parameterNames,
        public readonly array $middleware,
    ) {}

    public function identifier(): string
    {
        return $this->name ?? ($this->method.' '.$this->uri);
    }

    public function hasParameters(): bool
    {
        return $this->parameterNames !== [];
    }
}
