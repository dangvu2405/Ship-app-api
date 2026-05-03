<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class CostCategory extends CetaModel
{
    use SoftDeletes;

    public function tripCosts(): HasMany
    {
        return $this->hasMany(TripCost::class);
    }
}
