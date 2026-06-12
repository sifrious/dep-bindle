<?php

declare(strict_types=1);

namespace Maryeperry\Bindle\Storage\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $run_id
 * @property string $name
 * @property string $slug
 * @property string $kind
 * @property ?string $source_path
 * @property ?string $signature_hash
 */
final class Component extends BindleModel
{
    protected $table = 'components';

    public function run(): BelongsTo
    {
        return $this->belongsTo(Run::class);
    }

    public function props(): HasMany
    {
        return $this->hasMany(Prop::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ComponentVariant::class);
    }

    public function pageComponents(): HasMany
    {
        return $this->hasMany(PageComponent::class);
    }
}
