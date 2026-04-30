<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Quotation extends Model
{
    use BelongsToTenant;
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'transport_request_id',
        'pricing_rule_id',
        'code',
        'distance_km',
        'base_freight',
        'surcharges_total',
        'special_fees_total',
        'discount_total',
        'vat_amount',
        'cost_amount',
        'selling_price',
        'margin_percent',
        'status',
        'notes',
        'approved_at',
        'rejected_at',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'transport_request_id' => 'integer',
        'pricing_rule_id' => 'integer',
        'distance_km' => 'decimal:2',
        'base_freight' => 'decimal:2',
        'surcharges_total' => 'decimal:2',
        'special_fees_total' => 'decimal:2',
        'discount_total' => 'decimal:2',
        'vat_amount' => 'decimal:2',
        'cost_amount' => 'decimal:2',
        'selling_price' => 'decimal:2',
        'margin_percent' => 'decimal:4',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function transportRequest(): BelongsTo
    {
        return $this->belongsTo(TransportRequest::class);
    }

    public function pricingRule(): BelongsTo
    {
        return $this->belongsTo(PricingRule::class);
    }

    public function pricingItems(): HasMany
    {
        return $this->hasMany(QuotationPricingItem::class);
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(QuotationApproval::class);
    }
}

