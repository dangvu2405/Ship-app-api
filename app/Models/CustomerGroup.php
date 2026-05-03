<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerGroup extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'name',
        'description',
        'assigned_dispatcher_id',
        'is_active',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'assigned_dispatcher_id' => 'integer',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (CustomerGroup $customerGroup): void {
            if ($customerGroup->company_id !== null) {
                return;
            }

            $companyId = app(TenantContext::class)->getCompanyId();
            if ($companyId !== null) {
                $customerGroup->company_id = $companyId;
            }
        });
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class, 'group_id');
    }

    public function assignedDispatcher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_dispatcher_id');
    }
}
