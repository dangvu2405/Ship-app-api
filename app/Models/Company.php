<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Company extends Model
{
    use HasFactory, SoftDeletes, \App\Traits\HasAuditLogs;

    protected $fillable = [
        'code',
        'name',
        'tax_code',
        'address',
        'phone',
        'email',
        'status',
    ];

    protected $casts = [
        'status' => 'string',
    ];

    // Relationships
    public function offices(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Office::class);
    }

    public function payrolls(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Payroll::class);
    }

    public function payrollPeriods(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PayrollPeriod::class);
    }
}
