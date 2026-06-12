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
}
