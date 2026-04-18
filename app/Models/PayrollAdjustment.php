<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PayrollAdjustment extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $fillable = [
        'company_id',
        'payroll_id',
        'original_payroll_id',
        'driver_id',
        'type',
        'category',
        'amount',
        'reason',
        'source_type',
        'source_id',
        'approved_by',
    ];

    protected $casts = [
        'amount' => 'float',
    ];

    public function payroll(): BelongsTo
    {
        return $this->belongsTo(Payroll::class);
    }

    public function originalPayroll(): BelongsTo
    {
        return $this->belongsTo(Payroll::class, 'original_payroll_id');
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function isAddition(): bool
    {
        return $this->type === 'addition';
    }

    public function netAmount(): float
    {
        return $this->isAddition() ? $this->amount : -$this->amount;
    }
}
