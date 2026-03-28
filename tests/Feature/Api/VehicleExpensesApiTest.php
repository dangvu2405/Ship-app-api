<?php

namespace Tests\Feature\Api;

use App\Models\Company;
use App\Models\Office;
use App\Models\Vehicle;
use App\Models\VehicleExpense;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VehicleExpensesApiTest extends TestCase
{
    use RefreshDatabase;

    protected function getAdminUser()
    {
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $user = User::factory()->create(['status' => 'active']);
        $user->roles()->attach($adminRole->id);
        return $user;
    }

    public function test_vehicle_expenses_index_returns_list(): void
    {
        $admin = $this->getAdminUser();
        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/vehicle_expenses');

        $response->assertStatus(200)
            ->assertJsonStructure(['success', 'data']);
    }

    public function test_admin_can_create_vehicle_expense(): void
    {
        $admin = $this->getAdminUser();
        Sanctum::actingAs($admin);

        $company = Company::factory()->create();
        $office = Office::factory()->create(['company_id' => $company->id]);
        $vehicle = Vehicle::factory()->create(['office_id' => $office->id]);

        $response = $this->postJson('/api/vehicle_expenses', [
            'vehicle_id' => $vehicle->id,
            'type' => 'fuel',
            'amount' => 1500000,
            'expense_date' => '2026-03-15',
            'note' => 'Monthly fuel',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('vehicle_expenses', ['vehicle_id' => $vehicle->id, 'type' => 'fuel']);
    }

    public function test_admin_can_delete_vehicle_expense(): void
    {
        $admin = $this->getAdminUser();
        Sanctum::actingAs($admin);

        $company = Company::factory()->create();
        $office = Office::factory()->create(['company_id' => $company->id]);
        $vehicle = Vehicle::factory()->create(['office_id' => $office->id]);

        $ve = VehicleExpense::factory()->create(['vehicle_id' => $vehicle->id]);

        $response = $this->deleteJson('/api/vehicle_expenses/' . $ve->id);

        $response->assertStatus(200);
    }
}
