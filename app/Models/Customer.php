<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'type',
        'name',
        'tax_code',
        'phone',
        'email',
        'address',
    ];

    public function trips()
    {
        return $this->hasMany(Trip::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }
}
