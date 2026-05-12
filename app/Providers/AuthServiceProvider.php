<?php

namespace App\Providers;

use App\Models\CargoType;
use App\Models\ChatSession;
use App\Models\Company;
use App\Models\CostCategory;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Driver;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleAssignment;
use App\Models\VehicleType;
use App\Policies\CargoTypePolicy;
use App\Policies\ChatPolicy;
use App\Policies\CompanyPolicy;
use App\Policies\CostCategoryPolicy;
use App\Policies\CustomerGroupPolicy;
use App\Policies\CustomerPolicy;
use App\Policies\DriverPolicy;
use App\Policies\TripPolicy;
use App\Policies\UserPolicy;
use App\Policies\VehicleAssignmentPolicy;
use App\Policies\VehiclePolicy;
use App\Policies\VehicleTypePolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Trip::class => TripPolicy::class,
        User::class => UserPolicy::class,
        Customer::class => CustomerPolicy::class,
        Vehicle::class => VehiclePolicy::class,
        Driver::class => DriverPolicy::class,
        CargoType::class => CargoTypePolicy::class,
        CostCategory::class => CostCategoryPolicy::class,
        Company::class => CompanyPolicy::class,
        CustomerGroup::class => CustomerGroupPolicy::class,
        ChatSession::class => ChatPolicy::class,
        VehicleType::class => VehicleTypePolicy::class,
        VehicleAssignment::class => VehicleAssignmentPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();
    }
}
