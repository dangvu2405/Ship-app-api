<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TransportRequest extends Model
{
    use BelongsToTenant;
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'customer_id',
        'created_by',
        'code',
        'pickup_location',
        'delivery_location',
        'cargo_type',
        'cargo_weight',
        'cargo_volume',
        'requested_delivery_date',
        'service_type',
        'payment_term',
        'special_requirement',
        'fragile_flag',
        'cold_chain_flag',
        'dangerous_goods_flag',
        'loading_support_required',
        'insurance_required',
        'status',
        'rejection_reason',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'customer_id' => 'integer',
        'created_by' => 'integer',
        'cargo_weight' => 'decimal:2',
        'cargo_volume' => 'decimal:2',
        'requested_delivery_date' => 'date',
        'fragile_flag' => 'boolean',
        'cold_chain_flag' => 'boolean',
        'dangerous_goods_flag' => 'boolean',
        'loading_support_required' => 'boolean',
        'insurance_required' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function quotations(): HasMany
    {
        return $this->hasMany(Quotation::class);
    }
}

