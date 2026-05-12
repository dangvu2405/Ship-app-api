<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\VehicleType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VehicleTypesTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_can_get_a_list_of_vehicle_types()
    {
        VehicleType::factory()->count(3)->create(['company_id' => $this->company->id]);

        $response = $this->getJson('/api/vehicle-types');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    /** @test */
    public function it_can_create_a_vehicle_type()
    {
        $data = [
            'name' => 'Big Truck',
            'capacity' => 100,
        ];

        $response = $this->postJson('/api/vehicle-types', $data);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Big Truck');

        $this->assertDatabaseHas('vehicle_types', [
            'company_id' => $this->company->id,
            'name' => 'Big Truck',
        ]);
    }
}
