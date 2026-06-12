<?php

declare(strict_types=1);

namespace Maryeperry\Bindle\Facades;

use Illuminate\Support\Facades\Facade;

final class Bindle extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Maryeperry\Bindle\Bindle::class;
    }
}
