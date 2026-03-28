<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    use HasFactory, SoftDeletes;

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
    public function office()
    {
        return $this->belongsTo(Office::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function position()
    {
        return $this->belongsTo(Position::class);
    }

    public function driver()
    {
        return $this->hasOne(Driver::class);
    }

    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }

    public function employeeAllowances()
    {
        return $this->hasMany(EmployeeAllowance::class);
    }

    public function employeeDeductions()
    {
        return $this->hasMany(EmployeeDeduction::class);
    }

    public function trips()
    {
        return $this->hasMany(Trip::class, 'driver_id');
    }

    public function vehicleAssignments()
    {
        return $this->hasMany(VehicleAssignment::class, 'driver_id');
    }

    public function payrollDetails()
    {
        return $this->hasMany(PayrollDetail::class);
    }

    public function salaryConfigs()
    {
        return $this->hasMany(EmployeeSalaryConfig::class);
    }

    public function attendanceSummaries()
    {
        return $this->hasMany(AttendanceSummary::class);
    }

    public function user()
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
