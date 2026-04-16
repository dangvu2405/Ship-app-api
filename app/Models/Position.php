<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Concerns\BelongsToTenant;
use App\Tenancy\TenantContext;

class Position extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant;

    protected $fillable = [
        'company_id',
        'code',
        'name',
        'base_salary',
        'level',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'base_salary' => 'decimal:2',
        'level' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saving(function (Position $position): void {
            if ($position->company_id !== null) {
                return;
            }

            $companyId = app(TenantContext::class)->getCompanyId();
            if ($companyId !== null) {
                $position->company_id = $companyId;
            }
        });
    }

    // Relationships
    public function drivers(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Driver::class);
    }
}
