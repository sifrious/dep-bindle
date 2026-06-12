<?php

declare(strict_types=1);

namespace Maryeperry\Bindle\Storage\Models;

/**
 * @property int $id
 * @property string $subject_type
 * @property int $subject_id
 * @property int $viewport_w
 * @property int $viewport_h
 * @property ?string $viewport_label
 * @property string $path
 */
final class Screenshot extends BindleModel
{
    protected $table = 'screenshots';

    protected $casts = [
        'taken_at' => 'datetime',
    ];
}
