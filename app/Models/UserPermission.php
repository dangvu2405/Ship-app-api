<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class UserPermission extends CetaModel
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
