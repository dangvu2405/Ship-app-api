<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_normal_user_cannot_access_admin_endpoints(): void
    {
        // Normal user without admin role
        $user = User::factory()->create(['status' => 'active']);
        
        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/employees');

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Forbidden: Insufficient role',
            ]);
    }

    public function test_admin_can_access_admin_endpoints(): void
    {
        // User with admin role
        $adminRole = Role::factory()->create(['name' => 'admin']);
        $user = User::factory()->create(['status' => 'active']);
        $user->roles()->attach($adminRole->id);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/employees');

        // Can be 200 or 404/empty depends on DB, but NOT 403 Forbidden
        $response->assertStatus(200);
    }

    public function test_normal_user_can_access_their_own_endpoints(): void
    {
        // Normal user without admin role
        $user = User::factory()->create(['status' => 'active']);

        // Since my-salary and user endpoint is specifically designed for all users, 
        // 403 should not be returned. It may return 200 or something else.
        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/payrolls/my-salary');

        // We expect either 200 or maybe they don't have payrolls so empty, but definitely NOT 403
        $response->assertStatus(200);
    }
}
