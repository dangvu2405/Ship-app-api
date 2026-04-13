<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ViolationDispute extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'violation_id',
        'driver_id',
        'reason',
        'evidence_urls',
        'status',
        'resolved_by',
        'resolved_at',
        'resolution_note',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'evidence_urls' => 'array',
            'resolved_at'   => 'datetime',
        ];
    }

    public function violation(): BelongsTo
    {
        return $this->belongsTo(Violation::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', 'open');
    }
}
