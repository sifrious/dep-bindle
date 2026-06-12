<?php

declare(strict_types=1);

namespace Maryeperry\Bindle\Storage\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $run_id
 * @property ?string $route_name
 * @property string $uri
 * @property string $http_method
 * @property string $slug
 * @property ?string $controller
 * @property ?string $view
 * @property string $framework
 * @property ?string $html_hash
 */
final class Page extends BindleModel
{
    protected $table = 'pages';

    public function run(): BelongsTo
    {
        return $this->belongsTo(Run::class);
    }

    public function pageComponents(): HasMany
    {
        return $this->hasMany(PageComponent::class);
    }
}
