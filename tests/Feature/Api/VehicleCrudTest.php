<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class VehicleCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_paginated_vehicles(): void
    {
        Vehicle::factory()->count(3)->create(['company_id' => $this->company->id]);

        $response = $this->getJson('/api/vehicles');

        $response->assertOk()
            ->assertJsonStructure(['data', 'meta']);
    }

    public function test_index_only_returns_own_company_vehicles(): void
    {
        Vehicle::factory()->count(2)->create(['company_id' => $this->company->id]);
        Vehicle::factory()->count(4)->create();

        $response = $this->getJson('/api/vehicles');

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
    }

    public function test_store_creates_vehicle_with_valid_data(): void
    {
        $payload = [
            'plate_number' => '51A-12345',
            'type'         => 'truck',
            'brand'        => 'Isuzu',
            'model'        => 'D-Max',
            'year'         => 2022,
            'capacity'     => 2000,
            'status'       => 'active',
        ];

        $response = $this->postJson('/api/vehicles', $payload);

        $response->assertCreated()
            ->assertJsonPath('data.plate_number', '51A-12345')
            ->assertJsonStructure(['data' => ['id', 'plate_number', 'type', 'status']]);

        $this->assertDatabaseHas('vehicles', [
            'plate_number' => '51A-12345',
            'company_id'   => $this->company->id,
        ]);
    }

    public function test_store_requires_plate_number(): void
    {
        $response = $this->postJson('/api/vehicles', [
            'type' => 'truck',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['plate_number']);
    }

    public function test_store_requires_type(): void
    {
        $response = $this->postJson('/api/vehicles', [
            'plate_number' => '59A-99999',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['type']);
    }

    public function test_store_rejects_duplicate_plate_number(): void
    {
        Vehicle::factory()->create([
            'company_id'   => $this->company->id,
            'plate_number' => '51B-11111',
        ]);

        $response = $this->postJson('/api/vehicles', [
            'plate_number' => '51B-11111',
            'type'         => 'van',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['plate_number']);
    }

    public function test_store_rejects_invalid_status(): void
    {
        $response = $this->postJson('/api/vehicles', [
            'plate_number' => '59A-00001',
            'type'         => 'truck',
            'status'       => 'flying',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }

    public function test_show_returns_vehicle_of_own_company(): void
    {
        $vehicle = Vehicle::factory()->create(['company_id' => $this->company->id]);

        $response = $this->getJson("/api/vehicles/{$vehicle->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $vehicle->id);
    }

    public function test_show_denies_access_to_other_company_vehicle(): void
    {
        $other = Vehicle::factory()->create();

        $response = $this->getJson("/api/vehicles/{$other->id}");

        $response->assertForbidden();
    }

    public function test_update_modifies_vehicle_fields(): void
    {
        $vehicle = Vehicle::factory()->create([
            'company_id' => $this->company->id,
            'status'     => 'active',
        ]);

        $response = $this->putJson("/api/vehicles/{$vehicle->id}", [
            'status' => 'maintenance',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'maintenance');

        $this->assertDatabaseHas('vehicles', [
            'id'     => $vehicle->id,
            'status' => 'maintenance',
        ]);
    }

    public function test_update_denies_access_to_other_company_vehicle(): void
    {
        $other = Vehicle::factory()->create();

        $response = $this->putJson("/api/vehicles/{$other->id}", [
            'status' => 'inactive',
        ]);

        $response->assertForbidden();
    }

    public function test_destroy_soft_deletes_vehicle(): void
    {
        $vehicle = Vehicle::factory()->create(['company_id' => $this->company->id]);

        $response = $this->deleteJson("/api/vehicles/{$vehicle->id}");

        $response->assertNoContent();
        $this->assertSoftDeleted('vehicles', ['id' => $vehicle->id]);
    }

    public function test_destroy_denies_other_company_vehicle(): void
    {
        $other = Vehicle::factory()->create();

        $response = $this->deleteJson("/api/vehicles/{$other->id}");

        $response->assertForbidden();
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->app['auth']->forgetGuards();

        $response = $this->getJson('/api/vehicles');

        $response->assertUnauthorized();
    }
}
