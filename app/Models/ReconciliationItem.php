<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ReconciliationItem extends CetaModel
{
    public function session(): BelongsTo
    {
        return $this->belongsTo(ReconciliationSession::class, 'session_id');
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }
}
