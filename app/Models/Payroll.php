<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payroll extends Model
{
    use BelongsToTenant;
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'month',
        'year',
        'status',
        'locked_at',
        'approved_by',
        'approved_at',
        'paid_at',
        'paid_by',
        'notes',
        'snapshot_json',
    ];

    protected $casts = [
        'month'         => 'integer',
        'year'          => 'integer',
        'locked_at'     => 'datetime',
        'approved_at'   => 'datetime',
        'paid_at'       => 'datetime',
        'snapshot_json' => 'array',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PayrollLine::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function paidBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    public function isLocked(): bool
    {
        return $this->status === 'locked';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    /** A payroll that is locked or paid cannot be recalculated or modified. */
    public function isFrozen(): bool
    {
        return in_array($this->status, ['locked', 'paid'], true);
    }
}
