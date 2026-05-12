<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Driver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class DriverCrudTest extends TestCase
{
    use RefreshDatabase;

    private function validDriverPayload(array $overrides = []): array
    {
        return array_merge([
            'name'          => 'Nguyễn Văn Tài',
            'email'         => 'tai.nguyen@ceta.test',
            'phone'         => '0901234567',
            'license_no'    => 'DL'.fake()->unique()->numerify('#######'),
            'license_class' => 'C',
            'join_date'     => '2023-01-01',
            'gender'        => 'male',
            'status'        => 'active',
        ], $overrides);
    }

    public function test_index_returns_paginated_drivers(): void
    {
        Driver::factory()->count(3)->create(['company_id' => $this->company->id]);

        $response = $this->getJson('/api/drivers');

        $response->assertOk()
            ->assertJsonStructure(['data', 'meta']);
    }

    public function test_index_only_returns_own_company_drivers(): void
    {
        Driver::factory()->count(2)->create(['company_id' => $this->company->id]);
        Driver::factory()->count(3)->create();

        $response = $this->getJson('/api/drivers');

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
    }

    public function test_store_creates_driver_with_valid_data(): void
    {
        $payload = $this->validDriverPayload();

        $response = $this->postJson('/api/drivers', $payload);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Nguyễn Văn Tài')
            ->assertJsonStructure(['data' => ['id', 'name', 'email', 'phone', 'license_no']]);

        $this->assertDatabaseHas('drivers', [
            'email'      => 'tai.nguyen@ceta.test',
            'company_id' => $this->company->id,
        ]);
    }

    public function test_store_requires_name(): void
    {
        $payload = $this->validDriverPayload();
        unset($payload['name']);

        $response = $this->postJson('/api/drivers', $payload);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_store_requires_email(): void
    {
        $payload = $this->validDriverPayload();
        unset($payload['email']);

        $response = $this->postJson('/api/drivers', $payload);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_store_requires_valid_email(): void
    {
        $payload = $this->validDriverPayload(['email' => 'not-an-email']);

        $response = $this->postJson('/api/drivers', $payload);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_store_requires_license_no(): void
    {
        $payload = $this->validDriverPayload();
        unset($payload['license_no']);

        $response = $this->postJson('/api/drivers', $payload);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['license_no']);
    }

    public function test_store_requires_license_class(): void
    {
        $payload = $this->validDriverPayload();
        unset($payload['license_class']);

        $response = $this->postJson('/api/drivers', $payload);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['license_class']);
    }

    public function test_store_requires_unique_email(): void
    {
        Driver::factory()->create([
            'company_id' => $this->company->id,
            'email'      => 'taken@ceta.test',
        ]);

        $payload = $this->validDriverPayload(['email' => 'taken@ceta.test']);

        $response = $this->postJson('/api/drivers', $payload);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_store_requires_unique_phone(): void
    {
        Driver::factory()->create([
            'company_id' => $this->company->id,
            'phone'      => '0901111111',
        ]);

        $payload = $this->validDriverPayload(['phone' => '0901111111', 'email' => 'other@ceta.test']);

        $response = $this->postJson('/api/drivers', $payload);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['phone']);
    }

    public function test_store_validates_gender_enum(): void
    {
        $payload = $this->validDriverPayload(['gender' => 'unknown']);

        $response = $this->postJson('/api/drivers', $payload);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['gender']);
    }

    public function test_show_returns_driver_of_own_company(): void
    {
        $driver = Driver::factory()->create(['company_id' => $this->company->id]);

        $response = $this->getJson("/api/drivers/{$driver->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $driver->id);
    }

    public function test_show_denies_access_to_other_company_driver(): void
    {
        $other = Driver::factory()->create();

        $response = $this->getJson("/api/drivers/{$other->id}");

        $response->assertForbidden();
    }

    public function test_update_modifies_driver_status(): void
    {
        $driver = Driver::factory()->create([
            'company_id' => $this->company->id,
            'status'     => 'active',
        ]);

        $response = $this->putJson("/api/drivers/{$driver->id}", [
            'status' => 'inactive',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'inactive');

        $this->assertDatabaseHas('drivers', [
            'id'     => $driver->id,
            'status' => 'inactive',
        ]);
    }

    public function test_update_denies_access_to_other_company_driver(): void
    {
        $other = Driver::factory()->create();

        $response = $this->putJson("/api/drivers/{$other->id}", [
            'status' => 'inactive',
        ]);

        $response->assertForbidden();
    }

    public function test_destroy_soft_deletes_driver(): void
    {
        $driver = Driver::factory()->create(['company_id' => $this->company->id]);

        $response = $this->deleteJson("/api/drivers/{$driver->id}");

        $response->assertNoContent();
        $this->assertSoftDeleted('drivers', ['id' => $driver->id]);
    }

    public function test_destroy_denies_other_company_driver(): void
    {
        $other = Driver::factory()->create();

        $response = $this->deleteJson("/api/drivers/{$other->id}");

        $response->assertForbidden();
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->app['auth']->forgetGuards();

        $response = $this->getJson('/api/drivers');

        $response->assertUnauthorized();
    }
}
