<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Concerns\BelongsToTenant;
use App\Tenancy\TenantContext;

class Customer extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant;

    protected $fillable = [
        'company_id',
        'type',
        'name',
        'tax_code',
        'phone',
        'email',
        'address',
    ];

    protected $casts = [
        'company_id' => 'integer',
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

    public function trips(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Trip::class);
    }

    public function invoices(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Invoice::class);
    }
}
