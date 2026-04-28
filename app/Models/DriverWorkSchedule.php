<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOffice;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

final class DriverWorkSchedule extends Model
{
    use BelongsToOffice;
    use BelongsToTenant;
    use SoftDeletes;

    public static function getTenantThroughRelation(): ?string
    {
        return 'driver';
    }

    protected $fillable = [
        'driver_id',
        'company_id',
        'office_id',
        'work_date',
        'shift_code',
        'start_time',
        'end_time',
        'vehicle_id',
        'status',
        'notes',
        'hos_override_reason',
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
            'company_id' => 'integer',
            'work_date' => 'date',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'locked_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        self::creating(function (DriverWorkSchedule $schedule): void {
            if ($schedule->company_id !== null || $schedule->driver_id === null) {
                return;
            }

            $schedule->company_id = Driver::withoutGlobalScopes()
                ->whereKey($schedule->driver_id)
                ->value('company_id');
        });

        self::updating(function (DriverWorkSchedule $schedule): void {
            if (! $schedule->isDirty('driver_id')) {
                return;
            }

            $schedule->company_id = Driver::withoutGlobalScopes()
                ->whereKey($schedule->driver_id)
                ->value('company_id');
        });
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

    public function scopeForDriverId(Builder $query, int $driver_id): Builder
    {
        return $query->where('driver_id', $driver_id);
    }

    public function scopeForOfficeId(Builder $query, int $office_id): Builder
    {
        return $query->where('office_id', $office_id);
    }

    public function scopeForStatusFilter(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    public function isLocked(): bool
    {
        return $this->status === 'locked';
    }
}
