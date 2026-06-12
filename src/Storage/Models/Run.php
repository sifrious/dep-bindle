<?php

declare(strict_types=1);

namespace Maryeperry\Bindle\Storage\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $environment
 * @property string $status
 * @property string $bindle_version
 */
final class Run extends BindleModel
{
    protected $table = 'runs';

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function pages(): HasMany
    {
        return $this->hasMany(Page::class);
    }

    public function components(): HasMany
    {
        return $this->hasMany(Component::class);
    }

    public function errors(): HasMany
    {
        return $this->hasMany(ErrorLog::class);
    }
}
