<?php

declare(strict_types=1);

namespace Maryeperry\Bindle\Browser;

/**
 * Which browser a run was captured with. Recorded on every `runs` row so a
 * placeholder scan can never be mistaken for a real one after the fact.
 */
enum DriverKind: string
{
    case Placeholder = 'null';

    case Dusk = 'dusk';

    public static function fromOption(mixed $option): self
    {
        if (! is_string($option)) {
            return self::Placeholder;
        }

        return match (strtolower(trim($option))) {
            'dusk' => self::Dusk,
            default => self::Placeholder,
        };
    }

    public function producesRealScreenshots(): bool
    {
        return $this === self::Dusk;
    }

    public function label(): string
    {
        return match ($this) {
            self::Placeholder => 'no screenshots',
            self::Dusk => 'real screenshots',
        };
    }

    public function describe(): string
    {
        return match ($this) {
            self::Placeholder => 'Placeholder driver: routes, components and Markdown are real, but every screenshot is a 1x1 placeholder and the DOM is empty, so Alpine bindings are not discovered.',
            self::Dusk => 'Dusk driver: drives real Chrome against your running app, capturing full-page screenshots and the rendered DOM.',
        };
    }
}
