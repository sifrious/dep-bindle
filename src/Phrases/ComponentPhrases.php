<?php

declare(strict_types=1);

namespace Maryeperry\Bindle\Phrases;

use Maryeperry\Bindle\Storage\Models\Component;

final class ComponentPhrases
{
    public function __construct(
        private readonly Dictionary $dict,
        private readonly PropPhrases $propPhrases,
    ) {}

    public function describe(Component $component): string
    {
        $kindPhrase = $this->dict->table('component_kind')[$component->kind] ?? 'a component';

        $sentences = [
            sprintf('The component `%s` is %s.', $component->name, $kindPhrase),
        ];

        $propCount = $component->props()->count();
        if ($propCount === 0) {
            $sentences[] = 'It accepts no props.';
        } else {
            $sentences[] = sprintf('It accepts %d prop%s:', $propCount, $propCount === 1 ? '' : 's');
            foreach ($component->props as $prop) {
                $sentences[] = '- '.$this->propPhrases->describe($prop);
            }
        }

        return implode("\n", $sentences);
    }
}
