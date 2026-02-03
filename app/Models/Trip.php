<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Trip extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code',
        'customer_id',
        'driver_id',
        'vehicle_id',
        'start_point',
        'end_point',
        'distance_km',
        'start_time',
        'end_time',
        'price',
        'status',
    ];

    protected $casts = [
        'distance_km' => 'decimal:2',
        'price' => 'decimal:2',
        'start_time' => 'datetime',
        'end_time' => 'datetime',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function driver()
    {
        return $this->belongsTo(Employee::class, 'driver_id');
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function invoice()
    {
        return $this->hasOne(Invoice::class);
    }
}
