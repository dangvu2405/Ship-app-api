<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

final class DriverDocument extends CetaModel
{
    use SoftDeletes;

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }
}
