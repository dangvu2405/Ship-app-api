<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Trip extends Model
{
    use \App\Traits\HasAuditLogs, HasFactory, SoftDeletes;
    use BelongsToTenant;

    protected $fillable = [
        'company_id',
        'office_id',
        'code',
        'customer_id',
        'contact_name',
        'contact_phone',
        'cargo_type_id',
        'cargo_description',
        'cargo_quantity',
        'cargo_unit',
        'cargo_weight_ton',
        'cargo_notes',
        'transport_request_id',
        'quotation_id',
        'driver_id',
        'vehicle_id',
        'dispatcher_id',
        'assigned_at',
        'route_template_id',
        'origin_location_id',
        'destination_location_id',
        'start_point',
        'end_point',
        'received_date',
        'scheduled_date',
        'scheduled_time_from',
        'scheduled_time_to',
        'distance_km',
        'actual_distance_km',
        'start_time',
        'end_time',
        'actual_pickup_at',
        'actual_delivered_at',
        'price',
        'base_price',
        'surcharge_amount',
        'total_revenue',
        'payment_method',
        'payment_status',
        'status',
        'cancellation_reason',
        'cancelled_at',
        'cancelled_by',
        'internal_notes',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'office_id' => 'integer',
        'cargo_type_id' => 'integer',
        'transport_request_id' => 'integer',
        'quotation_id' => 'integer',
        'dispatcher_id' => 'integer',
        'route_template_id' => 'integer',
        'origin_location_id' => 'integer',
        'destination_location_id' => 'integer',
        'distance_km' => 'decimal:2',
        'cargo_quantity' => 'decimal:2',
        'cargo_weight_ton' => 'decimal:2',
        'actual_distance_km' => 'decimal:2',
        'price' => 'decimal:2',
        'base_price' => 'decimal:2',
        'surcharge_amount' => 'decimal:2',
        'total_revenue' => 'decimal:2',
        'received_date' => 'date',
        'scheduled_date' => 'date',
        'scheduled_time_from' => 'string',
        'scheduled_time_to' => 'string',
        'assigned_at' => 'datetime',
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'actual_pickup_at' => 'datetime',
        'actual_delivered_at' => 'datetime',
        'cancelled_at' => 'datetime',
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

    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function transportRequest(): BelongsTo
    {
        return $this->belongsTo(TransportRequest::class);
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'driver_id');
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function dispatcher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dispatcher_id');
    }

    public function routeTemplate(): BelongsTo
    {
        return $this->belongsTo(RouteTemplate::class, 'route_template_id');
    }

    public function originLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'origin_location_id');
    }

    public function destinationLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'destination_location_id');
    }

    public function cargoType(): BelongsTo
    {
        return $this->belongsTo(CargoType::class, 'cargo_type_id');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function stops(): HasMany
    {
        return $this->hasMany(TripStop::class);
    }

    public function surcharges(): HasMany
    {
        return $this->hasMany(TripSurcharge::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(TripDocument::class);
    }

    public function costs(): HasMany
    {
        return $this->hasMany(TripCost::class);
    }

    public function costApprovalRequests(): HasMany
    {
        return $this->hasMany(CostApprovalRequest::class);
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
