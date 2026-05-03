<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vehicle extends Model
{
    use \App\Traits\HasAuditLogs, HasFactory, SoftDeletes;
    use BelongsToTenant;

    protected $fillable = [
        'company_id',
        'vehicle_type_id',
        'plate_number',
        'type',
        'brand',
        'model',
        'year',
        'capacity',
        'max_load_ton',
        'volume_m3',
        'fuel_type',
        'fuel_consumption',
        'current_odometer_km',
        'status',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'vehicle_type_id' => 'integer',
        'year' => 'integer',
        'capacity' => 'integer',
        'max_load_ton' => 'decimal:2',
        'volume_m3' => 'decimal:2',
        'fuel_consumption' => 'decimal:2',
        'current_odometer_km' => 'decimal:2',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function vehicleType(): BelongsTo
    {
        return $this->belongsTo(VehicleType::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(VehicleAssignment::class);
    }

    public function trips(): HasMany
    {
        return $this->hasMany(Trip::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(VehicleDocument::class);
    }

    public function maintenanceSchedules(): HasMany
    {
        return $this->hasMany(MaintenanceSchedule::class);
    }

    public function maintenanceRecords(): HasMany
    {
        return $this->hasMany(MaintenanceRecord::class);
    }
}
