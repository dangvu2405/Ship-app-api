<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\CargoType;
use App\Models\Company;
use App\Models\CostCategory;
use App\Models\Office;
use App\Models\Role;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class CatalogsApiTest extends TestCase
{
    use RefreshDatabase;

    private function adminUser(): User
    {
        $adminRole = Role::query()->firstOrCreate(['name' => 'admin']);
        $user = User::factory()->create(['status' => 'active']);
        $user->roles()->syncWithoutDetaching([$adminRole->id]);

        return $user;
    }

    public function test_t03_01_create_catalog_records_successfully(): void
    {
        $company = Company::factory()->create();
        Sanctum::actingAs($this->adminUser());

        $vehicleType = $this->postJson('/api/v1/vehicle-types', [
            'name' => 'Xe tai 3 tan',
            'max_load_ton' => 3,
            'required_license_class' => 'C',
        ], $this->tenant_headers($company));
        $vehicleType->assertStatus(201)->assertJsonPath('data.name', 'Xe tai 3 tan');

        $cargoType = $this->postJson('/api/v1/cargo-types', [
            'name' => 'Hang lanh',
            'requires_special_vehicle' => true,
        ], $this->tenant_headers($company));
        $cargoType->assertStatus(201)->assertJsonPath('data.name', 'Hang lanh');

        $costCategory = $this->postJson('/api/v1/cost-categories', [
            'code' => 'FUEL',
            'name' => 'Nhien lieu',
            'requires_receipt' => true,
        ], $this->tenant_headers($company));
        $costCategory->assertStatus(201)->assertJsonPath('data.code', 'FUEL');
    }

    public function test_t03_02_duplicate_vehicle_type_name_in_same_company_returns_422(): void
    {
        $company = Company::factory()->create();
        Sanctum::actingAs($this->adminUser());

        VehicleType::query()->create([
            'company_id' => $company->id,
            'name' => 'Xe van',
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/v1/vehicle-types', [
            'name' => 'Xe van',
        ], $this->tenant_headers($company));

        $response->assertStatus(422)->assertJsonPath('success', false);
    }

    public function test_t03_03_update_sort_order_for_catalog_records(): void
    {
        $company = Company::factory()->create();
        Sanctum::actingAs($this->adminUser());

        $cargoType = CargoType::query()->create([
            'company_id' => $company->id,
            'name' => 'Hang thuong',
            'sort_order' => 1,
        ]);

        $response = $this->putJson('/api/v1/cargo-types/'.$cargoType->id, [
            'sort_order' => 9,
        ], $this->tenant_headers($company));

        $response->assertStatus(200);
        $this->assertDatabaseHas('cargo_types', [
            'id' => $cargoType->id,
            'sort_order' => 9,
        ]);
    }

    public function test_t03_04_delete_vehicle_type_in_use_returns_409(): void
    {
        $company = Company::factory()->create();
        Sanctum::actingAs($this->adminUser());

        $vehicleType = VehicleType::query()->create([
            'company_id' => $company->id,
            'name' => 'Xe dau keo',
        ]);

        $office = Office::factory()->create(['company_id' => $company->id]);

        Vehicle::factory()->create([
            'company_id' => $company->id,
            'office_id' => $office->id,
            'vehicle_type_id' => $vehicleType->id,
        ]);

        $response = $this->deleteJson('/api/v1/vehicle-types/'.$vehicleType->id, [], $this->tenant_headers($company));

        $response->assertStatus(409)->assertJsonPath('success', false);
    }

    public function test_t03_05_soft_disable_catalog_record(): void
    {
        $company = Company::factory()->create();
        Sanctum::actingAs($this->adminUser());

        $costCategory = CostCategory::query()->create([
            'company_id' => $company->id,
            'code' => 'WAITING',
            'name' => 'Phu phi cho',
            'is_active' => true,
        ]);

        $response = $this->putJson('/api/v1/cost-categories/'.$costCategory->id, [
            'is_active' => false,
        ], $this->tenant_headers($company));

        $response->assertStatus(200)->assertJsonPath('data.is_active', false);
        $this->assertDatabaseHas('cost_categories', [
            'id' => $costCategory->id,
            'is_active' => 0,
        ]);
    }

    public function test_t03_06_viewer_cannot_create_catalog(): void
    {
        $company = Company::factory()->create();
        Sanctum::actingAs(User::factory()->create(['status' => 'active']));

        $response = $this->postJson('/api/v1/cargo-types', [
            'name' => 'Hang de vo',
        ], $this->tenant_headers($company));

        $response->assertStatus(403);
    }
}
