<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Driver;
use App\Models\DriverDocument;
use App\Models\Office;
use App\Models\Role;
use App\Models\RouteTemplate;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class DriverDocumentsApiTest extends TestCase
{
    use RefreshDatabase;

    private function adminUser(array $override = []): User
    {
        $adminRole = Role::query()->firstOrCreate(['name' => 'admin']);
        $user = User::factory()->create(array_merge(['status' => 'active', 'role' => 'admin'], $override));
        $user->roles()->syncWithoutDetaching([$adminRole->id]);

        return $user;
    }

    public function test_t12_01_add_driver_license_document(): void
    {
        $company = Company::factory()->create();
        $office = Office::factory()->create(['company_id' => $company->id]);
        $driver = Driver::factory()->create(['company_id' => $company->id, 'office_id' => $office->id]);
        Sanctum::actingAs($this->adminUser());

        $response = $this->postJson('/api/v1/driver-documents', [
            'driver_id' => $driver->id,
            'doc_type' => 'driver_license',
            'doc_name' => 'GPLX hang C',
            'file_url' => 'https://cdn.example.com/docs/gplx.pdf',
            'expiry_date' => now()->addMonths(8)->toDateString(),
        ], $this->tenant_headers($company));

        $response->assertStatus(201)->assertJsonPath('data.doc_type', 'driver_license');
    }

    public function test_t12_02_expiring_driver_license_creates_notification(): void
    {
        $company = Company::factory()->create();
        $office = Office::factory()->create(['company_id' => $company->id]);
        $driver = Driver::factory()->create(['company_id' => $company->id, 'office_id' => $office->id]);
        Sanctum::actingAs($this->adminUser());

        $this->postJson('/api/v1/driver-documents', [
            'driver_id' => $driver->id,
            'doc_type' => 'driver_license',
            'doc_name' => 'GPLX sap het han',
            'file_url' => 'https://cdn.example.com/docs/gplx-expire.pdf',
            'expiry_date' => now()->addDays(7)->toDateString(),
            'alert_before_days' => 30,
        ], $this->tenant_headers($company))->assertStatus(201);

        $count = DB::table('notifications')
            ->where('type', 'driver_document_expiry')
            ->count();
        $this->assertGreaterThanOrEqual(1, $count);
    }

    public function test_t12_03_create_trip_with_expired_driver_license_returns_warning_but_allows(): void
    {
        $company = Company::factory()->create();
        $office = Office::factory()->create(['company_id' => $company->id]);
        $customer = Customer::factory()->create(['company_id' => $company->id]);
        $driver = Driver::factory()->create([
            'company_id' => $company->id,
            'office_id' => $office->id,
            'expired_date' => now()->subYear()->toDateString(),
        ]);
        $vehicle = Vehicle::factory()->create([
            'company_id' => $company->id,
            'office_id' => $office->id,
            'status' => 'active',
        ]);
        $route = RouteTemplate::query()->create(['company_id' => $company->id, 'name' => 'HN-HP']);
        Sanctum::actingAs($this->adminUser());

        DriverDocument::query()->create([
            'company_id' => $company->id,
            'driver_id' => $driver->id,
            'doc_type' => 'driver_license',
            'doc_name' => 'GPLX da het han',
            'file_url' => 'https://cdn.example.com/docs/expired-license.pdf',
            'expiry_date' => now()->subDay()->toDateString(),
        ]);

        $response = $this->postJson('/api/v1/trips', [
            'code' => 'TRIP-T12-03',
            'customer_id' => $customer->id,
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicle->id,
            'route_template_id' => $route->id,
            'start_point' => 'Ha Noi',
            'end_point' => 'Hai Phong',
            'status' => 'pending',
        ], $this->tenant_headers($company));

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.warnings.0', 'Driver license is expired. Trip is still allowed but requires follow-up.');
    }
}
