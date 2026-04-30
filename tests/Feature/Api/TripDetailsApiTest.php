<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Driver;
use App\Models\Office;
use App\Models\Role;
use App\Models\Trip;
use App\Models\TripStop;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class TripDetailsApiTest extends TestCase
{
    use RefreshDatabase;

    private function adminUser(): User
    {
        $adminRole = Role::query()->firstOrCreate(['name' => 'admin']);
        $admin = User::factory()->create(['status' => 'active']);
        $admin->roles()->syncWithoutDetaching([$adminRole->id]);

        return $admin;
    }

    private function createTrip(Company $company): Trip
    {
        $office = Office::factory()->create(['company_id' => $company->id]);
        $customer = Customer::factory()->create(['company_id' => $company->id]);
        $driver = Driver::factory()->create(['company_id' => $company->id, 'office_id' => $office->id, 'status' => 'active']);
        $vehicle = Vehicle::factory()->create(['company_id' => $company->id, 'office_id' => $office->id, 'status' => 'active']);

        return Trip::query()->create([
            'company_id' => $company->id,
            'office_id' => $office->id,
            'code' => 'TRIP-T18-'.uniqid(),
            'customer_id' => $customer->id,
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicle->id,
            'start_point' => 'A',
            'end_point' => 'B',
            'status' => 'pending',
        ]);
    }

    public function test_t18_01_upload_trip_document(): void
    {
        $company = Company::factory()->create();
        $trip = $this->createTrip($company);
        Sanctum::actingAs($this->adminUser());

        $this->postJson('/api/v1/trips/'.$trip->id.'/documents', [
            'doc_type' => 'delivery_receipt',
            'doc_name' => 'Proof',
            'file_url' => 'https://cdn.example.com/pod-1.pdf',
            'file_size_kb' => 220,
        ], $this->tenant_headers($company))
            ->assertStatus(201)
            ->assertJsonPath('data.trip_id', $trip->id);
    }

    public function test_t18_02_reject_trip_document_invalid_extension(): void
    {
        $company = Company::factory()->create();
        $trip = $this->createTrip($company);
        Sanctum::actingAs($this->adminUser());

        $this->postJson('/api/v1/trips/'.$trip->id.'/documents', [
            'doc_type' => 'delivery_receipt',
            'doc_name' => 'Invalid',
            'file_url' => 'https://cdn.example.com/bad.exe',
            'file_size_kb' => 20,
        ], $this->tenant_headers($company))->assertStatus(422);
    }

    public function test_t18_03_stop_status_transition_pending_to_arrived_to_completed(): void
    {
        $company = Company::factory()->create();
        $trip = $this->createTrip($company);
        $stop = TripStop::query()->create([
            'company_id' => $company->id,
            'trip_id' => $trip->id,
            'stop_type' => 'pickup',
            'sequence' => 1,
            'address' => 'A',
            'status' => 'pending',
        ]);
        Sanctum::actingAs($this->adminUser());

        $this->patchJson('/api/v1/trips/'.$trip->id.'/stops/'.$stop->id.'/status', [
            'status' => 'arrived',
        ], $this->tenant_headers($company))->assertStatus(200);

        $this->patchJson('/api/v1/trips/'.$trip->id.'/stops/'.$stop->id.'/status', [
            'status' => 'completed',
        ], $this->tenant_headers($company))->assertStatus(200)
            ->assertJsonPath('data.status', 'completed');
    }

    public function test_t18_04_invalid_stop_transition_returns_422(): void
    {
        $company = Company::factory()->create();
        $trip = $this->createTrip($company);
        $stop = TripStop::query()->create([
            'company_id' => $company->id,
            'trip_id' => $trip->id,
            'stop_type' => 'delivery',
            'sequence' => 1,
            'address' => 'B',
            'status' => 'pending',
        ]);
        Sanctum::actingAs($this->adminUser());

        $this->patchJson('/api/v1/trips/'.$trip->id.'/stops/'.$stop->id.'/status', [
            'status' => 'completed',
        ], $this->tenant_headers($company))->assertStatus(422);
    }

    public function test_t18_05_update_trip_internal_notes_and_actual_distance(): void
    {
        $company = Company::factory()->create();
        $trip = $this->createTrip($company);
        Sanctum::actingAs($this->adminUser());

        $this->patchJson('/api/v1/trips/'.$trip->id.'/details', [
            'internal_notes' => 'Gate delay due to weather',
            'actual_distance_km' => 128.5,
        ], $this->tenant_headers($company))
            ->assertStatus(200)
            ->assertJsonPath('data.internal_notes', 'Gate delay due to weather')
            ->assertJsonPath('data.actual_distance_km', '128.50');
    }
}
