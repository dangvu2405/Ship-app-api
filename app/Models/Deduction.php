<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Deduction extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code',
        'name',
    ];

    public function employeeDeductions()
    {
        return $this->hasMany(EmployeeDeduction::class);
    }
}
