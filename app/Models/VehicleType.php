<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class VehicleType extends CetaModel
{
    use SoftDeletes;

    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class);
    }
}
