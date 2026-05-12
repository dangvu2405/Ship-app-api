<?php

namespace App\Policies;

use App\Models\VehicleType;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class VehicleTypePolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, VehicleType $vehicleType): bool
    {
        return $user->company_id === $vehicleType->company_id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, VehicleType $vehicleType): bool
    {
        return $user->company_id === $vehicleType->company_id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, VehicleType $vehicleType): bool
    {
        return $user->company_id === $vehicleType->company_id;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, VehicleType $vehicleType): bool
    {
        return $user->company_id === $vehicleType->company_id;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, VehicleType $vehicleType): bool
    {
        return $user->company_id === $vehicleType->company_id;
    }

    /**
     * Determine whether the user can reorder models.
     */
    public function updateAny(User $user): bool
    {
        return true; // Assuming any user with update permission can reorder
    }
}
