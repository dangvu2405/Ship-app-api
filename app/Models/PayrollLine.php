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
    use BelongsToTenant;
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'payroll_id',
        'company_id',
        'driver_id',
        'base_salary',
        'trip_bonus',
        'overtime_pay',
        'night_shift_allowance',
        'public_holiday_pay',
        'allowance',
        'deduction',
        'leave_unpaid_deduction',
        'violation_deduction',
        'fuel_cost',
        'tax',
        'net_salary',
        'working_days',
        'leave_days_paid',
        'leave_days_unpaid',
        'overtime_hours',
        'trips_completed_count',
        'total_distance_km',
        'meta_json',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'base_salary' => 'decimal:2',
        'trip_bonus' => 'decimal:2',
        'overtime_pay' => 'decimal:2',
        'night_shift_allowance' => 'decimal:2',
        'public_holiday_pay' => 'decimal:2',
        'allowance' => 'decimal:2',
        'deduction' => 'decimal:2',
        'leave_unpaid_deduction' => 'decimal:2',
        'violation_deduction' => 'decimal:2',
        'fuel_cost' => 'decimal:2',
        'tax' => 'decimal:2',
        'net_salary' => 'decimal:2',
        'working_days' => 'integer',
        'leave_days_paid' => 'integer',
        'leave_days_unpaid' => 'integer',
        'overtime_hours' => 'decimal:2',
        'trips_completed_count' => 'integer',
        'total_distance_km' => 'decimal:2',
        'meta_json' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (PayrollLine $line): void {
            if ($line->company_id !== null) {
                return;
            }

            $companyId = Payroll::withoutGlobalScopes()
                ->whereKey($line->payroll_id)
                ->value('company_id');

            if ($companyId !== null) {
                $line->company_id = (int) $companyId;
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function payroll(): BelongsTo
    {
        return $this->belongsTo(Payroll::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }
}
