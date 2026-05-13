<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class LeaveRequest extends Model
{
    use BelongsToTenant;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'driver_id',
        'leave_type_id',
        'from_date',
        'to_date',
        'total_days',
        'reason',
        'status',
        'approved_by',
        'approved_at',
        'rejection_reason',
        'attachment_urls',
        'created_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'company_id' => 'integer',
            'from_date' => 'date',
            'to_date' => 'date',
            'total_days' => 'decimal:1',
            'approved_at' => 'datetime',
            'attachment_urls' => 'array',
        ];
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', 'approved');
    }

    public function scopeForDriver(Builder $query, int $driverId): Builder
    {
        return $query->where('driver_id', $driverId);
    }

    public function scopeOverlapping(Builder $query, string $from, string $to): Builder
    {
        return $query->where('from_date', '<=', $to)
            ->where('to_date', '>=', $from)
            ->whereNotIn('status', ['rejected', 'cancelled']);
    }
}
