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
        'month',
        'year',
        'status',
        'locked_at',
    ];

    protected $casts = [
        'month' => 'integer',
        'year' => 'integer',
        'locked_at' => 'datetime',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function details()
    {
        return $this->hasMany(PayrollDetail::class);
    }
}
