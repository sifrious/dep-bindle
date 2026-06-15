<?php

declare(strict_types=1);

namespace Maryeperry\Bindle\Storage\Models;

use Illuminate\Database\Eloquent\Model;
use Maryeperry\Bindle\Storage\Database\ConnectionFactory;

abstract class BindleModel extends Model
{
    protected $connection = ConnectionFactory::CONNECTION_NAME;

    public $timestamps = false;

    protected $guarded = [];

    /**
     * Bindle's internal models live on their own SQLite connection and are
     * only ever read inside one-shot console / Dusk runs. Opt them out of the
     * host application's `Model::preventLazyLoading()` (common in strict-mode
     * apps) so our own documentation generation never throws a
     * LazyLoadingViolationException on relations like props/variants.
     *
     * @param  string  $key
     */
    protected function handleLazyLoadingViolation($key): mixed
    {
        return null;
    }
}
