<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PayrollLine extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'payroll_id',
        'driver_id',
        'base_salary',
        'trip_bonus',
        'allowance',
        'deduction',
        'fuel_cost',
        'tax',
        'net_salary',
        'working_days',
        'trips_completed_count',
        'total_distance_km',
        'meta_json',
    ];

    protected $casts = [
        'base_salary' => 'decimal:2',
        'trip_bonus' => 'decimal:2',
        'allowance' => 'decimal:2',
        'deduction' => 'decimal:2',
        'fuel_cost' => 'decimal:2',
        'tax' => 'decimal:2',
        'net_salary' => 'decimal:2',
        'meta_json' => 'array',
    ];

    public function payroll(): BelongsTo
    {
        return $this->belongsTo(Payroll::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }
}
