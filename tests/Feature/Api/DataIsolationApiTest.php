<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Company;
use App\Models\Driver;
use App\Models\Customer;
use App\Models\Office;
use App\Models\User;
use App\Services\GeminiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Mockery\MockInterface;
use Tests\TestCase;

final class DataIsolationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_access_any_company_context(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();

        Office::factory()->create(['company_id' => $companyA->id]);
        Office::factory()->create(['company_id' => $companyB->id]);

        $superAdmin = User::factory()->create([
            'status' => 'active',
            'role' => 'super_admin',
        ]);
        Sanctum::actingAs($superAdmin);

        $response = $this->getJson('/api/v1/drivers', [
            'X-Tenant-ID' => (string) $companyB->id,
        ]);

        $response->assertStatus(200);
    }

    public function test_user_cannot_switch_to_other_company_by_header_token_context(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();

        $officeA = Office::factory()->create(['company_id' => $companyA->id, 'code' => 'OFF-A']);
        $officeB = Office::factory()->create(['company_id' => $companyB->id, 'code' => 'OFF-B']);

        $driverA = Driver::factory()->create([
            'company_id' => $companyA->id,
            'office_id' => $officeA->id,
            'name' => 'Driver Company A',
        ]);
        Driver::factory()->create([
            'company_id' => $companyB->id,
            'office_id' => $officeB->id,
            'name' => 'Driver Company B',
        ]);

        $user = User::factory()->create([
            'status' => 'active',
            'driver_id' => $driverA->id,
            'role' => 'viewer',
        ]);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/workforce/driver-schedules', [
            'X-Company-Id' => (string) $companyB->id,
        ]);

        $response->assertStatus(403);
    }

    public function test_t25_01_user_company_a_cannot_view_company_b_data(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();

        $officeA = Office::factory()->create(['company_id' => $companyA->id]);
        $officeB = Office::factory()->create(['company_id' => $companyB->id]);

        $driverA = Driver::factory()->create([
            'company_id' => $companyA->id,
            'office_id' => $officeA->id,
        ]);
        Driver::factory()->create([
            'company_id' => $companyB->id,
            'office_id' => $officeB->id,
        ]);

        $userA = User::factory()->create([
            'status' => 'active',
            'driver_id' => $driverA->id,
            'role' => 'viewer',
        ]);
        Sanctum::actingAs($userA);

        $response = $this->getJson('/api/v1/workforce/driver-schedules', [
            'X-Tenant-ID' => (string) $companyB->id,
        ]);

        $response->assertStatus(403);
    }

    public function test_t25_02_api_without_company_filter_returns_only_own_company_data(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();

        $officeA = Office::factory()->create(['company_id' => $companyA->id]);
        $officeB = Office::factory()->create(['company_id' => $companyB->id]);

        $driverA = Driver::factory()->create([
            'company_id' => $companyA->id,
            'office_id' => $officeA->id,
            'name' => 'Driver A',
        ]);
        $driverB = Driver::factory()->create([
            'company_id' => $companyB->id,
            'office_id' => $officeB->id,
            'name' => 'Driver B',
        ]);

        $userA = User::factory()->create([
            'status' => 'active',
            'driver_id' => $driverA->id,
            'role' => 'viewer',
        ]);
        Sanctum::actingAs($userA);

        $this->mock(GeminiService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('generateContent')
                ->once()
                ->andReturn([
                    'raw' => [],
                    'text' => 'OK',
                ]);
        });

        $response = $this->postJson('/api/v1/chat/messages', [
            'message' => 'Tóm tắt tình hình hiện tại của tôi',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.message.context.data.tài_xế', 'Driver A');
        $responseBody = json_encode($response->json(), JSON_UNESCAPED_UNICODE);
        $this->assertIsString($responseBody);
        $this->assertStringNotContainsString('Driver B', $responseBody);
    }

    public function test_t25_03_trip_creation_with_cross_company_references_returns_422(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();

        $officeA = Office::factory()->create(['company_id' => $companyA->id]);
        $officeB = Office::factory()->create(['company_id' => $companyB->id]);

        $driverA = Driver::factory()->create([
            'company_id' => $companyA->id,
            'office_id' => $officeA->id,
            'status' => 'active',
        ]);
        $vehicleA = \App\Models\Vehicle::factory()->create([
            'company_id' => $companyA->id,
            'office_id' => $officeA->id,
            'status' => 'active',
        ]);
        $customerB = Customer::factory()->create(['company_id' => $companyB->id]);

        $admin = User::factory()->create(['status' => 'active', 'role' => 'admin']);
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/v1/trips', [
            'customer_id' => $customerB->id,
            'driver_id' => $driverA->id,
            'vehicle_id' => $vehicleA->id,
            'code' => 'TRIP-CROSS-COMPANY-001',
            'start_point' => 'A',
            'end_point' => 'B',
            'status' => 'pending',
        ], $this->tenant_headers($companyA));

        $response->assertStatus(422)->assertJsonValidationErrors(['customer_id']);
    }
}

