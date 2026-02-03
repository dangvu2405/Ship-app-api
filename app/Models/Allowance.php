<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Allowance extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'default_amount',
        'taxable',
    ];

    protected $casts = [
        'default_amount' => 'decimal:2',
        'taxable' => 'boolean',
    ];

    public function employeeAllowances()
    {
        return $this->hasMany(EmployeeAllowance::class);
    }
}
