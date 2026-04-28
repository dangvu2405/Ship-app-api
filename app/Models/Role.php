<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Role extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'company_id',
    ];

    // Relationships

    /**
     * The company this role belongs to. NULL means it is a global/system role.
     */
    public function company(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function users(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_roles')
            ->withPivot('company_id')
            ->withTimestamps();
    }

    public function permissions(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permissions');
    }

    // Scopes

    /** Roles that apply across all companies (system-wide). */
    public function scopeGlobal(Builder $query): Builder
    {
        return $query->whereNull('company_id');
    }

    /** Roles scoped to a specific company. */
    public function scopeForCompany(Builder $query, int $companyId): Builder
    {
        return $query->where('company_id', $companyId);
    }

    /** Global roles plus the roles belonging to a given company. */
    public function scopeAvailableFor(Builder $query, int $companyId): Builder
    {
        return $query->where(fn (Builder $q) => $q
            ->whereNull('company_id')
            ->orWhere('company_id', $companyId)
        );
    }

    // Helpers

    public function isGlobal(): bool
    {
        return $this->company_id === null;
    }
}
