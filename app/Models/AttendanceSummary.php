<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AttendanceSummary extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'payroll_period_id',
        'employee_id',
        'working_days',
        'actual_days',
        'leave_paid_days',
        'leave_unpaid_days',
        'overtime_hours',
        'status',
        'approved_at',
        'approved_by',
        'source',
        'meta_json',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'working_days' => 'decimal:2',
        'actual_days' => 'decimal:2',
        'leave_paid_days' => 'decimal:2',
        'leave_unpaid_days' => 'decimal:2',
        'overtime_hours' => 'decimal:2',
        'approved_at' => 'datetime',
        'meta_json' => 'array',
    ];

    public function payrollPeriod()
    {
        return $this->belongsTo(PayrollPeriod::class);
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function approvedByUser()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function createdByUser()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedByUser()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function deletedByUser()
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }
}
