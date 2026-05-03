<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class CargoType extends CetaModel
{
    use SoftDeletes;

    public function trips(): HasMany
    {
        return $this->hasMany(Trip::class);
    }
}
