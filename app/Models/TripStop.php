<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class TripStop extends Model
{
    protected $fillable = [
        'company_id',
        'trip_id',
        'stop_type',
        'sequence',
        'location_id',
        'address',
        'contact_name',
        'contact_phone',
        'scheduled_time',
        'actual_time',
        'status',
        'notes',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'trip_id' => 'integer',
        'sequence' => 'integer',
        'location_id' => 'integer',
        'scheduled_time' => 'datetime',
        'actual_time' => 'datetime',
    ];

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }
}
