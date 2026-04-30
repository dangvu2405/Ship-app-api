<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

final class MaintenanceSchedule extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'vehicle_id',
        'spare_part_id',
        'task_name',
        'interval_type',
        'interval_km',
        'interval_days',
        'last_done_km',
        'last_done_date',
        'next_due_km',
        'next_due_date',
        'alert_before_km',
        'alert_before_days',
        'estimated_cost',
        'notes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'company_id' => 'integer',
            'vehicle_id' => 'integer',
            'spare_part_id' => 'integer',
            'interval_km' => 'integer',
            'interval_days' => 'integer',
            'last_done_km' => 'decimal:2',
            'last_done_date' => 'date',
            'next_due_km' => 'decimal:2',
            'next_due_date' => 'date',
            'alert_before_km' => 'integer',
            'alert_before_days' => 'integer',
            'estimated_cost' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function sparePart(): BelongsTo
    {
        return $this->belongsTo(SparePart::class);
    }
}
