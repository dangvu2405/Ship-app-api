<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

final class TripCost extends Model
{
    use BelongsToTenant;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'trip_id',
        'cost_category_id',
        'amount',
        'norm_amount',
        'description',
        'receipt_file_url',
        'incurred_date',
        'status',
        'approval_required',
        'approved_by',
        'approved_at',
        'approval_note',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'company_id' => 'integer',
            'trip_id' => 'integer',
            'cost_category_id' => 'integer',
            'amount' => 'decimal:2',
            'norm_amount' => 'decimal:2',
            'incurred_date' => 'date',
            'approval_required' => 'boolean',
            'approved_by' => 'integer',
            'approved_at' => 'datetime',
        ];
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function costCategory(): BelongsTo
    {
        return $this->belongsTo(CostCategory::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
