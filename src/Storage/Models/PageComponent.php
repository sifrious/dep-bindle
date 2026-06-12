<?php

declare(strict_types=1);

namespace Maryeperry\Bindle\Storage\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $page_id
 * @property int $component_id
 * @property ?int $parent_component_id
 * @property int $depth
 * @property ?array $props_passed
 */
final class PageComponent extends BindleModel
{
    protected $table = 'page_components';

    protected $casts = [
        'props_passed' => 'array',
        'depth' => 'int',
    ];

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    public function component(): BelongsTo
    {
        return $this->belongsTo(Component::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Component::class, 'parent_component_id');
    }
}
