<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\VehicleType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class VehicleTypesTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_can_get_a_list_of_vehicle_types(): void
    {
        VehicleType::factory()->count(3)->create(['company_id' => $this->company->id]);

        $response = $this->getJson('/api/vehicle-types');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    public function test_it_can_create_a_vehicle_type(): void
    {
        $data = [
            'name'     => 'Big Truck',
            'capacity' => 100,
        ];

        $response = $this->postJson('/api/vehicle-types', $data);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Big Truck');

        $this->assertDatabaseHas('vehicle_types', [
            'company_id' => $this->company->id,
            'name'       => 'Big Truck',
        ]);
    }

    public function test_vehicle_type_list_is_company_scoped(): void
    {
        VehicleType::factory()->count(2)->create(['company_id' => $this->company->id]);
        VehicleType::factory()->count(5)->create();

        $response = $this->getJson('/api/vehicle-types');

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
    }

    public function test_create_vehicle_type_requires_name(): void
    {
        $response = $this->postJson('/api/vehicle-types', [
            'max_load_ton' => 5.0,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_vehicle_type_is_soft_deleted(): void
    {
        $type = VehicleType::factory()->create(['company_id' => $this->company->id]);

        $response = $this->deleteJson("/api/vehicle-types/{$type->id}");

        $response->assertNoContent();
        $this->assertSoftDeleted('vehicle_types', ['id' => $type->id]);
    }
}
