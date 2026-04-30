<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Driver;
use App\Models\Office;
use App\Models\PriceList;
use App\Models\PriceListItem;
use App\Models\Role;
use App\Models\RouteTemplate;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class PriceListsApiTest extends TestCase
{
    use RefreshDatabase;

    private function adminUser(): User
    {
        $adminRole = Role::query()->firstOrCreate(['name' => 'admin']);
        $user = User::factory()->create(['status' => 'active']);
        $user->roles()->syncWithoutDetaching([$adminRole->id]);

        return $user;
    }

    public function test_t06_01_create_price_list_with_null_effective_to(): void
    {
        $company = Company::factory()->create();
        $customer = Customer::factory()->create(['company_id' => $company->id]);
        Sanctum::actingAs($this->adminUser());

        $response = $this->postJson('/api/v1/price-lists', [
            'customer_id' => $customer->id,
            'name' => 'Bang gia vo thoi han',
            'effective_from' => now()->toDateString(),
            'effective_to' => null,
        ], $this->tenant_headers($company));

        $response->assertStatus(201)->assertJsonPath('data.effective_to', null);
    }

    public function test_t06_02_trip_creation_auto_resolves_price_from_price_list_item(): void
    {
        $company = Company::factory()->create();
        Sanctum::actingAs($this->adminUser());

        $office = Office::factory()->create(['company_id' => $company->id]);
        $customer = Customer::factory()->create(['company_id' => $company->id]);
        $vehicleType = VehicleType::query()->create(['company_id' => $company->id, 'name' => 'Xe tai 5 tan']);
        $vehicle = Vehicle::factory()->create([
            'company_id' => $company->id,
            'office_id' => $office->id,
            'vehicle_type_id' => $vehicleType->id,
            'status' => 'active',
        ]);
        $driver = Driver::factory()->create(['company_id' => $company->id, 'office_id' => $office->id]);
        $routeTemplate = RouteTemplate::query()->create([
            'company_id' => $company->id,
            'name' => 'HN-HP',
        ]);

        $priceList = PriceList::query()->create([
            'company_id' => $company->id,
            'customer_id' => $customer->id,
            'name' => 'Bang gia KH A',
            'effective_from' => now()->subDays(3)->toDateString(),
            'effective_to' => null,
            'is_active' => true,
        ]);
        PriceListItem::query()->create([
            'company_id' => $company->id,
            'price_list_id' => $priceList->id,
            'route_template_id' => $routeTemplate->id,
            'vehicle_type_id' => $vehicleType->id,
            'price' => 1500000,
            'price_unit' => 'per_trip',
        ]);

        $response = $this->postJson('/api/v1/trips', [
            'code' => 'TRIP-T06-02',
            'customer_id' => $customer->id,
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicle->id,
            'route_template_id' => $routeTemplate->id,
            'start_point' => 'Ha Noi',
            'end_point' => 'Hai Phong',
            'status' => 'pending',
        ], $this->tenant_headers($company));

        $response->assertStatus(201)->assertJsonPath('data.base_price', '1500000.00');
    }

    public function test_t06_03_overlapping_price_lists_pick_latest_effective_from(): void
    {
        $company = Company::factory()->create();
        Sanctum::actingAs($this->adminUser());

        $customer = Customer::factory()->create(['company_id' => $company->id]);
        $routeTemplate = RouteTemplate::query()->create(['company_id' => $company->id, 'name' => 'QN-DN']);

        $older = PriceList::query()->create([
            'company_id' => $company->id,
            'customer_id' => $customer->id,
            'name' => 'Bang gia cu',
            'effective_from' => now()->subDays(10)->toDateString(),
            'effective_to' => null,
            'is_active' => true,
        ]);
        PriceListItem::query()->create([
            'company_id' => $company->id,
            'price_list_id' => $older->id,
            'route_template_id' => $routeTemplate->id,
            'price' => 1100000,
            'price_unit' => 'per_trip',
        ]);

        $newer = PriceList::query()->create([
            'company_id' => $company->id,
            'customer_id' => $customer->id,
            'name' => 'Bang gia moi',
            'effective_from' => now()->subDays(2)->toDateString(),
            'effective_to' => null,
            'is_active' => true,
        ]);
        PriceListItem::query()->create([
            'company_id' => $company->id,
            'price_list_id' => $newer->id,
            'route_template_id' => $routeTemplate->id,
            'price' => 1300000,
            'price_unit' => 'per_trip',
        ]);

        $office = Office::factory()->create(['company_id' => $company->id]);
        $vehicle = Vehicle::factory()->create([
            'company_id' => $company->id,
            'office_id' => $office->id,
            'status' => 'active',
        ]);
        $driver = Driver::factory()->create(['company_id' => $company->id, 'office_id' => $office->id]);

        $response = $this->postJson('/api/v1/trips', [
            'code' => 'TRIP-T06-03',
            'customer_id' => $customer->id,
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicle->id,
            'route_template_id' => $routeTemplate->id,
            'start_point' => 'Quang Ninh',
            'end_point' => 'Da Nang',
            'status' => 'pending',
        ], $this->tenant_headers($company));

        $response->assertStatus(201)->assertJsonPath('data.base_price', '1300000.00');
    }

    public function test_t06_04_effective_to_before_effective_from_returns_422(): void
    {
        $company = Company::factory()->create();
        $customer = Customer::factory()->create(['company_id' => $company->id]);
        Sanctum::actingAs($this->adminUser());

        $response = $this->postJson('/api/v1/price-lists', [
            'customer_id' => $customer->id,
            'name' => 'Bang gia loi ngay',
            'effective_from' => '2026-05-10',
            'effective_to' => '2026-05-01',
        ], $this->tenant_headers($company));

        $response->assertStatus(422)->assertJsonPath('success', false);
    }
}
