<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Position extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'base_salary',
        'level',
    ];

    protected $casts = [
        'base_salary' => 'decimal:2',
        'level' => 'integer',
    ];

    // Relationships
    public function employees()
    {
        return $this->hasMany(Employee::class);
    }
}
