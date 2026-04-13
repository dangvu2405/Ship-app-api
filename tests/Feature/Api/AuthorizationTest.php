<?php

namespace Tests\Feature\Api;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_normal_user_cannot_access_admin_endpoints(): void
    {
        $user = User::factory()->create(['status' => 'active']);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/drivers');

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Forbidden: Insufficient role',
            ]);
    }

    public function test_admin_can_access_admin_endpoints(): void
    {
        $adminRole = Role::factory()->create(['name' => 'admin']);
        $user = User::factory()->create(['status' => 'active']);
        $user->roles()->attach($adminRole->id);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/drivers');

        $response->assertStatus(200);
    }
}
