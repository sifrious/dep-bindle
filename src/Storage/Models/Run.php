<?php

declare(strict_types=1);

namespace Maryeperry\Bindle\Storage\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Maryeperry\Bindle\Browser\DriverKind;

/**
 * @property int $id
 * @property string $environment
 * @property string $status
 * @property string $bindle_version
 * @property string $driver
 */
final class Run extends BindleModel
{
    protected $table = 'runs';

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function driverKind(): DriverKind
    {
        return DriverKind::fromOption($this->driver);
    }

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
