<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PricingRule extends Model
{
    use BelongsToTenant;
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'office_id',
        'customer_id',
        'name',
        'base_freight',
        'rate_per_km',
        'fuel_adjustment',
        'max_discount_percent',
        'minimum_margin_percent',
        'effective_from',
        'effective_to',
        'is_active',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'office_id' => 'integer',
        'customer_id' => 'integer',
        'base_freight' => 'decimal:2',
        'rate_per_km' => 'decimal:4',
        'fuel_adjustment' => 'decimal:2',
        'max_discount_percent' => 'decimal:2',
        'minimum_margin_percent' => 'decimal:2',
        'effective_from' => 'date',
        'effective_to' => 'date',
        'is_active' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function quotations(): HasMany
    {
        return $this->hasMany(Quotation::class);
    }
}

