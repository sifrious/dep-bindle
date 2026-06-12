<?php

declare(strict_types=1);

namespace Maryeperry\Bindle\Storage\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $component_id
 * @property string $variant_name
 * @property array $props_combo
 */
final class ComponentVariant extends BindleModel
{
    protected $table = 'component_variants';

    protected $casts = [
        'props_combo' => 'array',
    ];

    public function component(): BelongsTo
    {
        return $this->belongsTo(Component::class);
    }
}
