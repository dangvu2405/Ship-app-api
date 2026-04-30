<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class TripStatusHistory extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $fillable = [
        'company_id',
        'trip_id',
        'from_status',
        'to_status',
        'changed_by',
        'changed_at',
        'reason',
        'note',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'company_id' => 'integer',
            'trip_id' => 'integer',
            'changed_by' => 'integer',
            'changed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }
}
