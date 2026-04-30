<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

final class RouteTemplate extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'name',
        'origin_location_id',
        'destination_location_id',
        'distance_km',
        'estimated_hours',
        'default_price',
        'fuel_norm_liter',
        'toll_norm',
        'notes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'company_id' => 'integer',
            'origin_location_id' => 'integer',
            'destination_location_id' => 'integer',
            'distance_km' => 'decimal:2',
            'estimated_hours' => 'decimal:1',
            'default_price' => 'decimal:2',
            'fuel_norm_liter' => 'decimal:2',
            'toll_norm' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
