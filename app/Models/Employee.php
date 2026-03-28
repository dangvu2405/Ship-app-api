<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    use HasFactory, SoftDeletes, \App\Traits\HasAuditLogs;

    protected $fillable = [
        'code',
        'name',
        'email',
        'phone',
        'dob',
        'gender',
        'address',
        'office_id',
        'department_id',
        'position_id',
        'type',
        'status',
        'join_date',
        'resign_date',
    ];

    protected $casts = [
        'dob' => 'date',
        'join_date' => 'date',
        'resign_date' => 'date',
    ];

    // Relationships
    public function office(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Office::class);
    }

    public function department(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function position(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    public function driver(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Driver::class);
    }

    public function attendances(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function employeeAllowances(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(EmployeeAllowance::class);
    }

    public function employeeDeductions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(EmployeeDeduction::class);
    }

    public function trips(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Trip::class, 'driver_id');
    }

    public function vehicleAssignments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(VehicleAssignment::class, 'driver_id');
    }

    public function payrollDetails(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PayrollDetail::class);
    }

    public function salaryConfigs(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(EmployeeSalaryConfig::class);
    }

    public function attendanceSummaries(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(AttendanceSummary::class);
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(User::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeDrivers($query)
    {
        return $query->where('type', 'driver');
    }

    public function scopeOfficeStaff($query)
    {
        return $query->where('type', 'office');
    }
}
