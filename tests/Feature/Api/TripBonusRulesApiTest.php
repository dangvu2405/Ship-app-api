<?php

namespace Tests\Feature\Api;

use App\Models\Role;
use App\Models\TripBonusRule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TripBonusRulesApiTest extends TestCase
{
    use RefreshDatabase;

    protected function getAdminUser()
    {
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $user = User::factory()->create(['status' => 'active']);
        $user->roles()->attach($adminRole->id);

        return $user;
    }

    public function test_trip_bonus_rules_index_returns_paginated_list(): void
    {
        $admin = $this->getAdminUser();
        Sanctum::actingAs($admin);

        TripBonusRule::factory()->count(2)->create();

        $response = $this->getJson('/api/trip_bonus_rules');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'data',
                    'meta',
                ],
            ]);
    }

    public function test_admin_can_create_trip_bonus_rule(): void
    {
        $admin = $this->getAdminUser();
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/trip_bonus_rules', [
            'min_km' => 0,
            'max_km' => 100,
            'bonus_per_km' => 1200,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.bonus_per_km', '1200.00');

        $this->assertDatabaseHas('trip_bonus_rules', [
            'min_km' => 0,
            'max_km' => 100,
            'bonus_per_km' => 1200,
        ]);
    }

    public function test_admin_can_update_trip_bonus_rule(): void
    {
        $admin = $this->getAdminUser();
        Sanctum::actingAs($admin);

        $rule = TripBonusRule::factory()->create([
            'min_km' => 0,
            'max_km' => 100,
            'bonus_per_km' => 900,
        ]);

        $response = $this->putJson('/api/trip_bonus_rules/' . $rule->id, [
            'min_km' => 101,
            'max_km' => 300,
            'bonus_per_km' => 1500,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.bonus_per_km', '1500.00');

        $this->assertDatabaseHas('trip_bonus_rules', [
            'id' => $rule->id,
            'min_km' => 101,
            'max_km' => 300,
            'bonus_per_km' => 1500,
        ]);
    }

    public function test_max_km_must_be_greater_than_min_km(): void
    {
        $admin = $this->getAdminUser();
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/trip_bonus_rules', [
            'min_km' => 200,
            'max_km' => 100,
            'bonus_per_km' => 1000,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('max_km');
    }

    public function test_admin_can_delete_trip_bonus_rule(): void
    {
        $admin = $this->getAdminUser();
        Sanctum::actingAs($admin);

        $rule = TripBonusRule::factory()->create();

        $response = $this->deleteJson('/api/trip_bonus_rules/' . $rule->id);

        $response->assertStatus(200);
        $this->assertSoftDeleted('trip_bonus_rules', ['id' => $rule->id]);
    }
}
