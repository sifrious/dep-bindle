<?php

declare(strict_types=1);

namespace Maryeperry\Bindle\Phrases;

use Maryeperry\Bindle\Storage\Models\Prop;

final class PropPhrases
{
    public function __construct(
        private readonly Dictionary $dict,
    ) {}

    /**
     * Accepts either a DiscoveredProp (during scanning) or a persisted Prop
     * (during Markdown generation). Both shapes expose the same properties.
     */
    public function describe(object $prop): string
    {
        $name = (string) ($prop->name ?? '');
        $type = $this->stringOrNull($prop->type ?? null);
        $required = (bool) ($prop->required ?? false);
        $default = $this->stringOrNull(
            $prop instanceof Prop ? ($prop->default_value ?? null) : ($prop->defaultValue ?? null)
        );

        $key = $this->templateKey($required, $type !== null, $default !== null);
        $template = $this->dict->table('prop_describe')[$key] ?? '';

        return $this->dict->render($template, [
            'name' => $name,
            'type' => $type ?? '',
            'default' => $default ?? '',
        ]);
    }

    private function templateKey(bool $required, bool $hasType, bool $hasDefault): string
    {
        if ($required) {
            return $hasType ? 'required_typed' : 'required_untyped';
        }
        if ($hasDefault) {
            return $hasType ? 'optional_with_default_typed' : 'optional_with_default_untyped';
        }

        return $hasType ? 'optional_no_default_typed' : 'optional_no_default_untyped';
    }

    private function stringOrNull(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }
}
