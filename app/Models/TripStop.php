<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class TripStop extends CetaModel
{
    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }
}
