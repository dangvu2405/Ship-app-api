<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class ReconciliationSession extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'company_id',
        'customer_id',
        'period_from',
        'period_to',
        'total_trips',
        'total_revenue',
        'adjusted_amount',
        'final_amount',
        'status',
        'confirmed_at',
        'confirmed_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'company_id' => 'integer',
            'customer_id' => 'integer',
            'period_from' => 'date',
            'period_to' => 'date',
            'total_trips' => 'integer',
            'total_revenue' => 'decimal:2',
            'adjusted_amount' => 'decimal:2',
            'final_amount' => 'decimal:2',
            'confirmed_at' => 'datetime',
            'confirmed_by' => 'integer',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ReconciliationItem::class, 'session_id');
    }
}
