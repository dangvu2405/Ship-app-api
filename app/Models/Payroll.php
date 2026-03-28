<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payroll extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'payroll_period_id',
        'month',
        'year',
        'status',
        'locked_at',
        'calculated_at',
        'calculated_by',
        'approved_at',
        'approved_by',
        'paid_at',
        'notes',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'month' => 'integer',
        'year' => 'integer',
        'locked_at' => 'datetime',
        'calculated_at' => 'datetime',
        'approved_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function payrollPeriod()
    {
        return $this->belongsTo(PayrollPeriod::class);
    }

    public function calculatedByUser()
    {
        return $this->belongsTo(User::class, 'calculated_by');
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

    public function details()
    {
        return $this->hasMany(PayrollDetail::class);
    }
}
