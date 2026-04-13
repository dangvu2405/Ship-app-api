<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class DriverWorkSchedule extends Model
{
    use BelongsToTenant;
    use SoftDeletes;

    protected $fillable = [
        'driver_id',
        'office_id',
        'work_date',
        'shift_code',
        'start_time',
        'end_time',
        'vehicle_id',
        'status',
        'notes',
        'submitted_by',
        'submitted_at',
        'approved_by',
        'approved_at',
        'locked_by',
        'locked_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'work_date'    => 'date',
            'submitted_at' => 'datetime',
            'approved_at'  => 'datetime',
            'locked_at'    => 'datetime',
        ];
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class);
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function scopeForDate(Builder $query, string $date): Builder
    {
        return $query->where('work_date', $date);
    }

    public function scopeForPeriod(Builder $query, string $from, string $to): Builder
    {
        return $query->whereBetween('work_date', [$from, $to]);
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', 'approved');
    }

    public function isLocked(): bool
    {
        return $this->status === 'locked';
    }
}
