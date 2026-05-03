<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class SparePart extends CetaModel
{
    use SoftDeletes;

    protected $casts = [
        'company_id' => 'integer',
        'replacement_interval_km' => 'integer',
        'replacement_interval_days' => 'integer',
        'estimated_unit_cost' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function maintenanceSchedules(): HasMany
    {
        return $this->hasMany(MaintenanceSchedule::class);
    }
}
