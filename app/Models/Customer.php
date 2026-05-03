<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Concerns\BelongsToTenant;
use App\Tenancy\TenantContext;

class Customer extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant;

    protected $fillable = [
        'company_id',
        'code',
        'type',
        'company_name',
        'name',
        'full_name',
        'extra_contact_name',
        'extra_contact_phone',
        'tax_code',
        'phone',
        'email',
        'address',
        'group_id',
        'assigned_dispatcher_id',
        'credit_limit',
        'payment_terms_days',
        'contract_file_url',
        'contract_start_date',
        'contract_end_date',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'group_id' => 'integer',
        'assigned_dispatcher_id' => 'integer',
        'credit_limit' => 'decimal:2',
        'payment_terms_days' => 'integer',
        'contract_start_date' => 'date',
        'contract_end_date' => 'date',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (Customer $customer): void {
            if ($customer->company_id !== null) {
                return;
            }

            $companyId = app(TenantContext::class)->getCompanyId();
            if ($companyId !== null) {
                $customer->company_id = $companyId;
            }
        });
    }

    public function trips(): HasMany
    {
        return $this->hasMany(Trip::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(CustomerGroup::class, 'group_id');
    }

    public function priceLists(): HasMany
    {
        return $this->hasMany(PriceList::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(PaymentRecord::class);
    }
}
