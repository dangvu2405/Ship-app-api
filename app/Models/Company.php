<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Company extends Model
{
    use HasFactory, SoftDeletes;

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
    public function offices()
    {
        return $this->hasMany(Office::class);
    }

    public function payrolls()
    {
        return $this->hasMany(Payroll::class);
    }
}
