<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class VehicleAssignment extends Model
{
    use BelongsToTenant;
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'vehicle_id',
        'driver_id',
        'from_date',
        'to_date',
        'release_reason',
        'notes',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'from_date' => 'date',
        'to_date' => 'date',
    ];

    protected static function booted(): void
    {
        static::saving(function (VehicleAssignment $assignment): void {
            if ($assignment->driver_id === null) {
                return;
            }

            $companyId = Driver::query()->whereKey($assignment->driver_id)->value('company_id');

            if ($companyId !== null) {
                $assignment->company_id = (int) $companyId;
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'driver_id');
    }
}
