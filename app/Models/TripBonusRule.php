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
        'company_id',
        'effective_from',
        'effective_to',
        'min_km',
        'max_km',
        'bonus_per_km',
    ];

    protected $casts = [
        'effective_from' => 'date',
        'effective_to' => 'date',
        'min_km' => 'decimal:2',
        'max_km' => 'decimal:2',
        'bonus_per_km' => 'decimal:2',
    ];
}
