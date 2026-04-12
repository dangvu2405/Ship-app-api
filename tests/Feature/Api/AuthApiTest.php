<?php

namespace Tests\Feature\Api;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_v1_login_returns_token_and_user(): void
    {
        $user = User::factory()->create([
            'email' => 'login@example.com',
            'password' => bcrypt('secret123'),
            'status' => 'active',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'login@example.com',
            'password' => 'secret123',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Login successful',
            ])
            ->assertJsonStructure([
                'data' => [
                    'user' => ['id', 'email'],
                    'token',
                ],
            ])
            ->assertJsonPath('data.user.id', $user->id);
    }

    public function test_v1_login_returns_401_for_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'exists@example.com',
            'password' => bcrypt('right'),
            'status' => 'active',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'exists@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_v1_login_returns_422_when_validation_fails(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'not-an-email',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Validation failed',
            ]);
    }

    public function test_user_endpoint_requires_auth(): void
    {
        $response = $this->getJson('/api/v1/user');

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_user_endpoint_returns_user_info_for_authenticated_user(): void
    {
        $user = User::factory()->create([
            'email' => 'user@example.com',
            'status' => 'active',
        ]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/user');

        $response->assertStatus(200)
            ->assertJsonFragment([
                'email' => 'user@example.com',
            ]);
    }

    public function test_auth_me_requires_auth(): void
    {
        $response = $this->getJson('/api/v1/auth/me');

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_auth_me_matches_user_payload(): void
    {
        $user = User::factory()->create([
            'email' => 'me@example.com',
            'status' => 'active',
        ]);

        $me = $this->actingAs($user, 'sanctum')->getJson('/api/v1/auth/me');
        $legacy = $this->actingAs($user, 'sanctum')->getJson('/api/v1/user');

        $me->assertStatus(200);
        $legacy->assertStatus(200);
        $this->assertSame($me->json('data.email'), $legacy->json('data.email'));
    }

    public function test_logout_invalidates_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('auth-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => "Bearer $token",
        ])->postJson('/api/v1/auth/logout');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Logout successful',
            ]);

        $this->assertCount(0, $user->tokens);
    }

    public function test_refresh_token_yields_new_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('auth-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => "Bearer $token",
        ])->postJson('/api/v1/auth/refresh');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Token refreshed successfully',
            ])
            ->assertJsonStructure([
                'data' => [
                    'token',
                ],
            ]);

        $this->assertCount(1, $user->tokens);
        $this->assertStringNotContainsString(explode('|', $token)[1] ?? '', $response->json('data.token'));
    }

    public function test_admin_can_register_new_user(): void
    {
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $admin = User::factory()->create(['status' => 'active']);
        $admin->roles()->attach($adminRole->id);

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/v1/auth/register', [
            'username' => 'newadminuser',
            'email' => 'newadmin@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Registration successful',
            ]);

        $this->assertDatabaseHas('users', [
            'username' => 'newadminuser',
            'email' => 'newadmin@example.com',
        ]);
    }

    public function test_normal_user_cannot_register_user(): void
    {
        $user = User::factory()->create(['status' => 'active']);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/auth/register', [
            'username' => 'newadminuser2',
            'email' => 'newadmin2@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(403);
    }

    public function test_v1_auth_test_accounts_returns_list_in_testing_env(): void
    {
        User::factory()->create([
            'email' => 'seeded@example.com',
            'username' => 'seeded',
            'status' => 'active',
        ]);

        $response = $this->getJson('/api/v1/auth/test-accounts');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'accounts',
                    'hint',
                ],
            ]);
    }
}
