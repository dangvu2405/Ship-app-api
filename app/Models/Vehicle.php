<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOffice;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vehicle extends Model
{
    use BelongsToOffice;
    use BelongsToTenant;
    use HasFactory, SoftDeletes, \App\Traits\HasAuditLogs;

    protected $fillable = [
        'company_id',
        'office_id',
        'plate_number',
        'type',
        'brand',
        'model',
        'year',
        'capacity',
        'status',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'year' => 'integer',
        'capacity' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saving(function (Vehicle $vehicle): void {
            if ($vehicle->office_id === null) {
                return;
            }

            $companyId = Office::query()->whereKey($vehicle->office_id)->value('company_id');

            if ($companyId !== null) {
                $vehicle->company_id = (int) $companyId;
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

    public function assignments(): HasMany
    {
        return $this->hasMany(VehicleAssignment::class);
    }

    public function trips(): HasMany
    {
        return $this->hasMany(Trip::class);
    }
}
