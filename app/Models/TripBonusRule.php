<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TripBonusRule extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'min_km',
        'max_km',
        'bonus_per_km',
    ];

    protected $casts = [
        'min_km' => 'decimal:2',
        'max_km' => 'decimal:2',
        'bonus_per_km' => 'decimal:2',
    ];
}
