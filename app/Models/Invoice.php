<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code',
        'trip_id',
        'customer_id',
        'subtotal',
        'vat_rate',
        'vat_amount',
        'total_amount',
        'status',
        'issued_at',
        'paid_at',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'vat_rate' => 'decimal:2',
        'vat_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'issued_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    public function trip()
    {
        return $this->belongsTo(Trip::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
