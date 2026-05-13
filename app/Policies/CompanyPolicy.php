<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Support\Facades\Schema;

class CompanyPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true; // All authenticated users can view companies they belong to
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Company $company): bool
    {
        // Check if user is associated with this company
        return $this->belongsToCompany($user, $company)
            || $user->hasRole('admin')
            || $user->hasRole('super_admin');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // Only super-admins can create new companies
        return $user->hasRole('super_admin');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Company $company): bool
    {
        // Only admins of this company or super-admins can update
        return ($this->belongsToCompany($user, $company) && $user->hasRole('admin'))
            || $user->hasRole('super_admin');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Company $company): bool
    {
        // Deleting a company is a sensitive operation, usually restricted
        // For now, let's disallow it via API for regular users
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Company $company): bool
    {
        return false; // Not typically allowed via API for regular users
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Company $company): bool
    {
        return false; // Not typically allowed via API for regular users
    }

    private function belongsToCompany(User $user, Company $company): bool
    {
        if (Schema::hasTable('user_companies')) {
            return $user->companies()->where('companies.id', $company->id)->exists();
        }

        return (int) $user->getAttribute('company_id') === (int) $company->id;
    }
}
