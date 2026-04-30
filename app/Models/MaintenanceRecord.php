<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

final class MaintenanceRecord extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'vehicle_id',
        'maintenance_schedule_id',
        'type',
        'title',
        'description',
        'odometer_km',
        'started_date',
        'completed_date',
        'garage_name',
        'total_cost',
        'invoice_number',
        'file_url',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'company_id' => 'integer',
            'vehicle_id' => 'integer',
            'maintenance_schedule_id' => 'integer',
            'odometer_km' => 'decimal:2',
            'started_date' => 'date',
            'completed_date' => 'date',
            'total_cost' => 'decimal:2',
        ];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function maintenanceSchedule(): BelongsTo
    {
        return $this->belongsTo(MaintenanceSchedule::class);
    }
}
