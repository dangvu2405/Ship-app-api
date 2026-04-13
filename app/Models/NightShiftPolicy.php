<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class NightShiftPolicy extends Model
{
    use BelongsToTenant;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'start_hour',
        'end_hour',
        'differential_pct',
        'effective_from',
        'effective_to',
        'is_active',
        'created_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'start_hour'       => 'integer',
            'end_hour'         => 'integer',
            'differential_pct' => 'decimal:2',
            'effective_from'   => 'date',
            'effective_to'     => 'date',
            'is_active'        => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActiveForCompany(Builder $query, int $companyId): Builder
    {
        return $query->where('company_id', $companyId)->where('is_active', true);
    }

    public function scopeEffectiveOn(Builder $query, string $date): Builder
    {
        return $query->where('effective_from', '<=', $date)
            ->where(fn (Builder $q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $date));
    }
}
