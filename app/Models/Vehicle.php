<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vehicle extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'office_id',
        'plate_number',
        'type',
        'brand',
        'model',
        'year',
        'capacity',
        'status',
    ];

    protected $casts = [
        'year' => 'integer',
        'capacity' => 'integer',
    ];

    public function office()
    {
        return $this->belongsTo(Office::class);
    }

    public function assignments()
    {
        return $this->hasMany(VehicleAssignment::class);
    }

    public function expenses()
    {
        return $this->hasMany(VehicleExpense::class);
    }

    public function trips()
    {
        return $this->hasMany(Trip::class);
    }
}
