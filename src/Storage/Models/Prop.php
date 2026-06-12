<?php

declare(strict_types=1);

namespace Maryeperry\Bindle\Storage\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $component_id
 * @property string $name
 * @property ?string $type
 * @property ?string $default_value
 * @property bool $required
 * @property string $source
 * @property ?string $description_phrase
 */
final class Prop extends BindleModel
{
    protected $table = 'props';

    protected $casts = [
        'required' => 'bool',
    ];

    public function component(): BelongsTo
    {
        return $this->belongsTo(Component::class);
    }
}
