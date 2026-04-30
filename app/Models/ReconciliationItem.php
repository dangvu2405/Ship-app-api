<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ReconciliationItem extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'company_id',
        'session_id',
        'trip_id',
        'original_amount',
        'adjusted_amount',
        'adjustment_reason',
        'is_disputed',
        'dispute_note',
    ];

    protected function casts(): array
    {
        return [
            'company_id' => 'integer',
            'session_id' => 'integer',
            'trip_id' => 'integer',
            'original_amount' => 'decimal:2',
            'adjusted_amount' => 'decimal:2',
            'is_disputed' => 'boolean',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(ReconciliationSession::class, 'session_id');
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }
}
