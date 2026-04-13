<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Violation extends Model
{
    use BelongsToTenant;
    use SoftDeletes;

    protected $fillable = [
        'driver_id',
        'company_id',
        'trip_id',
        'type',
        'occurred_at',
        'reported_by',
        'description',
        'penalty_amount',
        'status',
        'confirmed_by',
        'confirmed_at',
        'waived_by',
        'waived_at',
        'waive_reason',
        'evidence_urls',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'occurred_at'    => 'datetime',
            'confirmed_at'   => 'datetime',
            'waived_at'      => 'datetime',
            'penalty_amount' => 'decimal:2',
            'evidence_urls'  => 'array',
        ];
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function dispute(): HasOne
    {
        return $this->hasOne(ViolationDispute::class);
    }

    public function scopeConfirmed(Builder $query): Builder
    {
        return $query->where('status', 'confirmed');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopeForDriver(Builder $query, int $driverId): Builder
    {
        return $query->where('driver_id', $driverId);
    }

    public function scopeForPeriod(Builder $query, string $from, string $to): Builder
    {
        return $query->whereBetween('occurred_at', [$from.' 00:00:00', $to.' 23:59:59']);
    }

    public function isConfirmed(): bool
    {
        return $this->status === 'confirmed';
    }
}
