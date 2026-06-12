<?php

declare(strict_types=1);

namespace Maryeperry\Bindle\Storage\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property ?int $run_id
 * @property string $phase
 * @property ?string $subject_type
 * @property ?int $subject_id
 * @property string $severity
 * @property string $message
 * @property ?array $context
 */
final class ErrorLog extends BindleModel
{
    protected $table = 'errors';

    protected $casts = [
        'context' => 'array',
        'occurred_at' => 'datetime',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(Run::class);
    }
}
