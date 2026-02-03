<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PayrollDetail extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'payroll_id',
        'employee_id',
        'base_salary',
        'working_days',
        'overtime',
        'bonus',
        'allowance',
        'deduction',
        'fuel_cost',
        'tax',
        'net_salary',
        'meta_json',
    ];

    protected $casts = [
        'base_salary' => 'decimal:2',
        'working_days' => 'integer',
        'overtime' => 'decimal:2',
        'bonus' => 'decimal:2',
        'allowance' => 'decimal:2',
        'deduction' => 'decimal:2',
        'fuel_cost' => 'decimal:2',
        'tax' => 'decimal:2',
        'net_salary' => 'decimal:2',
        'meta_json' => 'array',
    ];

    public function payroll()
    {
        return $this->belongsTo(Payroll::class);
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function adjustments()
    {
        return $this->hasMany(PayrollAdjustment::class);
    }
}
