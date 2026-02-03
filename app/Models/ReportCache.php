<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ReportCache extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'type',
        'month',
        'year',
        'data_json',
        'expires_at',
    ];

    protected $casts = [
        'month' => 'integer',
        'year' => 'integer',
        'data_json' => 'array',
        'expires_at' => 'datetime',
    ];
}
