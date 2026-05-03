<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class DriverTeam extends CetaModel
{
    use SoftDeletes;

    public function manager(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'manager_id');
    }

    public function drivers(): HasMany
    {
        return $this->hasMany(Driver::class, 'team_id');
    }
}
