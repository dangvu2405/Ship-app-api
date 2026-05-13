<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Driver;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Seeder;

class CleanDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::factory()->create(['name' => 'ABC Transport']);

        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $user->companies()->attach($company->id, ['is_default' => true]);

        Customer::factory()->count(10)->create(['company_id' => $company->id]);
        Driver::factory()->count(5)->create(['company_id' => $company->id]);
        Vehicle::factory()->count(5)->create(['company_id' => $company->id]);
    }
}
