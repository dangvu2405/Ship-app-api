<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Driver;
use App\Models\Office;
use App\Models\Role;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class AuditIntegrityApiTest extends TestCase
{
    use RefreshDatabase;

    private function adminUser(): User
    {
        $admin = User::factory()->create(['status' => 'active']);
        $admin->roles()->syncWithoutDetaching([
            Role::firstOrCreate(['name' => 'admin'])->id,
        ]);

        return $admin;
    }

    public function test_t26_01_create_action_writes_audit_log(): void
    {
        $admin = $this->adminUser();
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/v1/companies', [
            'name' => 'Audit Company',
            'code' => 'AUD-COMPANY-001',
            'tax_code' => 'TAX-AUD-001',
            'status' => 'active',
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'action' => 'POST /api/v1/companies',
        ]);
    }

    public function test_t26_02_update_trip_keeps_old_and_new_data(): void
    {
        $admin = $this->adminUser();
        Sanctum::actingAs($admin);

        $company = Company::factory()->create();
        $office = Office::factory()->create(['company_id' => $company->id]);
        $vehicle = Vehicle::factory()->create(['office_id' => $office->id, 'status' => 'active']);
        $driver = Driver::factory()->create(['office_id' => $office->id, 'status' => 'active']);
        $customer = Customer::factory()->create(['company_id' => $company->id]);
        $trip = Trip::factory()->create([
            'company_id' => $company->id,
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'customer_id' => $customer->id,
            'status' => 'pending',
        ]);

        $update = $this->putJson("/api/v1/trips/{$trip->id}", [
            'status' => 'assigned',
        ], $this->tenant_headers($company));

        $update->assertStatus(200);

        $log = \App\Models\AuditLog::query()
            ->where('table_name', 'trips')
            ->where('record_id', $trip->id)
            ->where('action', 'updated')
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $this->assertIsArray($log->old_data);
        $this->assertIsArray($log->new_data);
        $this->assertSame('pending', $log->old_data['status'] ?? null);
        $this->assertSame('assigned', $log->new_data['status'] ?? null);
    }

    public function test_t26_03_delete_user_request_is_audited(): void
    {
        $admin = $this->adminUser();
        Sanctum::actingAs($admin);

        $victim = User::factory()->create(['status' => 'active']);
        $delete = $this->deleteJson("/api/v1/users/{$victim->id}");
        $delete->assertStatus(200);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'action' => "DELETE /api/v1/users/{$victim->id}",
        ]);
    }

    public function test_t26_04_audit_logs_cannot_be_deleted_via_http(): void
    {
        $admin = $this->adminUser();
        Sanctum::actingAs($admin);

        $delete = $this->deleteJson('/api/v1/audit-logs/1');
        $delete->assertStatus(404);
    }

    public function test_t26_05_auth_actions_filters_by_username(): void
    {
        $admin = $this->adminUser();
        $target = User::factory()->create([
            'status' => 'active',
            'username' => 'audit.target',
        ]);

        Sanctum::actingAs($target);
        $this->getJson('/api/v1/notifications/count')->assertStatus(200);

        Sanctum::actingAs($admin);
        $response = $this->getJson('/api/v1/auth/actions?username=audit.target');
        $response->assertStatus(200)->assertJsonPath('success', true);

        foreach ($response->json('data', []) as $row) {
            $this->assertSame('audit.target', $row['username'] ?? null);
        }
    }
}

