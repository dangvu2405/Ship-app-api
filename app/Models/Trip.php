<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Chuyến xe — `$fillable` / quan hệ bám schema `trips` baseline (ship_db dump 2026-04-23):
 * company_id, code, customer_id, driver_id, vehicle_id, start_point, end_point, distance_km,
 * start_time, end_time, price, status (+ timestamps, soft deletes).
 */
class Trip extends Model
{
    use \App\Traits\HasAuditLogs, HasFactory, SoftDeletes;
    use BelongsToTenant;

    protected $fillable = [
        'company_id',
        'code',
        'customer_id',
        'driver_id',
        'vehicle_id',
        'start_point',
        'end_point',
        'distance_km',
        'start_time',
        'end_time',
        'price',
        'status',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'customer_id' => 'integer',
        'driver_id' => 'integer',
        'vehicle_id' => 'integer',
        'distance_km' => 'decimal:2',
        'price' => 'decimal:2',
        'start_time' => 'datetime',
        'end_time' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (Trip $trip): void {
            if ($trip->driver_id !== null) {
                $company_id = Driver::withoutGlobalScopes()
                    ->whereKey($trip->driver_id)
                    ->value('company_id');
                if ($company_id !== null) {
                    $trip->company_id = (int) $company_id;

                    return;
                }
            }

            if ($trip->vehicle_id !== null) {
                $company_id = Vehicle::withoutGlobalScopes()
                    ->whereKey($trip->vehicle_id)
                    ->value('company_id');
                if ($company_id !== null) {
                    $trip->company_id = (int) $company_id;
                }
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'driver_id');
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(TripStatusHistory::class);
    }

    public function invoice(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Invoice::class);
    }
}
