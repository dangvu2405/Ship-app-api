<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Driver;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Company $companyB;
    private User $userB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->companyB = Company::factory()->create();
        $this->userB    = User::factory()->create(['role' => 'admin']);
        $this->userB->setAttribute('company_id', $this->companyB->id);
    }

    private function actAsCompanyB(): void
    {
        Sanctum::actingAs($this->userB);
        $this->withHeader('X-Tenant-ID', (string) $this->companyB->id);
    }

    public function test_company_b_cannot_see_company_a_customers(): void
    {
        Customer::factory()->count(3)->create(['company_id' => $this->company->id]);
        Customer::factory()->count(2)->create(['company_id' => $this->companyB->id]);

        $this->actAsCompanyB();

        $response = $this->getJson('/api/customers');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(2, $data);

        $returnedIds = collect($data)->pluck('company_id')->unique()->all();
        $this->assertNotContains($this->company->id, $returnedIds);
    }

    public function test_company_b_cannot_see_company_a_vehicles(): void
    {
        Vehicle::factory()->count(3)->create(['company_id' => $this->company->id]);
        Vehicle::factory()->count(1)->create(['company_id' => $this->companyB->id]);

        $this->actAsCompanyB();

        $response = $this->getJson('/api/vehicles');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    public function test_company_b_cannot_see_company_a_drivers(): void
    {
        Driver::factory()->count(2)->create(['company_id' => $this->company->id]);
        Driver::factory()->count(3)->create(['company_id' => $this->companyB->id]);

        $this->actAsCompanyB();

        $response = $this->getJson('/api/drivers');

        $response->assertOk();
        $this->assertCount(3, $response->json('data'));
    }

    public function test_company_b_cannot_show_company_a_customer(): void
    {
        $customerA = Customer::factory()->create(['company_id' => $this->company->id]);

        $this->actAsCompanyB();

        $response = $this->getJson("/api/customers/{$customerA->id}");

        $response->assertForbidden();
    }

    public function test_company_b_cannot_update_company_a_customer(): void
    {
        $customerA = Customer::factory()->create(['company_id' => $this->company->id]);

        $this->actAsCompanyB();

        $response = $this->putJson("/api/customers/{$customerA->id}", [
            'name' => 'Hacked by B',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('customers', ['id' => $customerA->id, 'name' => 'Hacked by B']);
    }

    public function test_company_b_cannot_delete_company_a_customer(): void
    {
        $customerA = Customer::factory()->create(['company_id' => $this->company->id]);

        $this->actAsCompanyB();

        $response = $this->deleteJson("/api/customers/{$customerA->id}");

        $response->assertForbidden();
        $this->assertDatabaseHas('customers', ['id' => $customerA->id, 'deleted_at' => null]);
    }

    public function test_company_b_cannot_update_company_a_vehicle(): void
    {
        $vehicleA = Vehicle::factory()->create(['company_id' => $this->company->id]);

        $this->actAsCompanyB();

        $response = $this->putJson("/api/vehicles/{$vehicleA->id}", [
            'status' => 'inactive',
        ]);

        $response->assertForbidden();
    }

    public function test_company_b_cannot_delete_company_a_vehicle(): void
    {
        $vehicleA = Vehicle::factory()->create(['company_id' => $this->company->id]);

        $this->actAsCompanyB();

        $response = $this->deleteJson("/api/vehicles/{$vehicleA->id}");

        $response->assertForbidden();
        $this->assertDatabaseHas('vehicles', ['id' => $vehicleA->id, 'deleted_at' => null]);
    }

    public function test_company_b_cannot_update_company_a_driver(): void
    {
        $driverA = Driver::factory()->create(['company_id' => $this->company->id]);

        $this->actAsCompanyB();

        $response = $this->putJson("/api/drivers/{$driverA->id}", [
            'status' => 'inactive',
        ]);

        $response->assertForbidden();
    }

    public function test_company_b_cannot_delete_company_a_driver(): void
    {
        $driverA = Driver::factory()->create(['company_id' => $this->company->id]);

        $this->actAsCompanyB();

        $response = $this->deleteJson("/api/drivers/{$driverA->id}");

        $response->assertForbidden();
    }

    public function test_trip_index_scoped_to_company_via_explicit_where(): void
    {
        $customerA = Customer::factory()->create(['company_id' => $this->company->id]);
        $this->postJson('/api/trips', [
            'customer_id'    => $customerA->id,
            'received_date'  => '2026-05-01',
            'scheduled_date' => '2026-05-02',
            'base_price'     => 1000000,
            'stops'          => [
                ['stop_type' => 'pickup', 'sequence' => 1, 'address' => 'HCM'],
                ['stop_type' => 'delivery', 'sequence' => 2, 'address' => 'HN'],
            ],
        ]);

        $this->actAsCompanyB();

        $response = $this->getJson('/api/trips');

        $response->assertOk();
        $this->assertCount(0, $response->json('data'));
    }
}
