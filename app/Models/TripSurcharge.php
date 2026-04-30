<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class TripSurcharge extends Model
{
    protected $fillable = ['company_id', 'trip_id', 'name', 'amount', 'notes'];

    protected $casts = [
        'company_id' => 'integer',
        'trip_id' => 'integer',
        'amount' => 'decimal:2',
    ];

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }
}
