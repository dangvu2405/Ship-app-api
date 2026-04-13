<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_email_password_login_route_is_removed(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'any@example.com',
            'password' => 'password',
        ]);

        $response->assertStatus(404);
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

    public function test_lark_redirect_returns_302_to_lark_oauth(): void
    {
        config()->set('lark.app_id', 'cli_test_123');
        config()->set('lark.oauth.redirect_uri', 'http://localhost:5173/auth/lark/callback');
        config()->set('lark.oauth.scope', 'contact:user.base:readonly');

        $response = $this->get('/api/v1/lark/oauth/redirect');

        $response->assertStatus(302);
        $this->assertStringContainsString('open.larksuite.com', (string) $response->headers->get('Location'));
    }

    public function test_lark_callback_logs_in_existing_lark_user(): void
    {
        config()->set('lark.app_id', 'cli_test_123');
        config()->set('lark.app_secret', 'secret_test_123');
        config()->set('lark.oauth.redirect_uri', 'http://localhost:5173/auth/lark/callback');

        $user = User::factory()->create([
            'email' => 'lark.user@example.com',
            'lark_user_id' => 'ou_existing_1',
            'status' => 'active',
        ]);

        Cache::put('lark:oauth:state:test_state_1', true, now()->addMinutes(10));
        Http::fake([
            'https://open.larksuite.com/open-apis/authen/v2/oauth/token' => Http::response([
                'code' => 0,
                'data' => ['access_token' => 'oauth_access_token'],
            ], 200),
            'https://open.larksuite.com/open-apis/authen/v1/user_info' => Http::response([
                'code' => 0,
                'data' => [
                    'user_id' => 'ou_existing_1',
                    'email' => 'lark.user@example.com',
                    'name' => 'Lark Existing',
                ],
            ], 200),
        ]);

        $response = $this->getJson('/api/v1/lark/oauth/callback?code=oauth_code_abc&state=test_state_1');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Lark login successful',
            ])
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonStructure([
                'data' => ['access_token', 'user'],
            ]);
    }

    public function test_lark_callback_links_existing_email_user(): void
    {
        config()->set('lark.app_id', 'cli_test_123');
        config()->set('lark.app_secret', 'secret_test_123');
        config()->set('lark.oauth.redirect_uri', 'http://localhost:5173/auth/lark/callback');

        $user = User::factory()->create([
            'email' => 'email.match@example.com',
            'lark_user_id' => null,
            'status' => 'active',
        ]);

        Cache::put('lark:oauth:state:test_state_2', true, now()->addMinutes(10));
        Http::fake([
            'https://open.larksuite.com/open-apis/authen/v2/oauth/token' => Http::response([
                'code' => 0,
                'data' => ['access_token' => 'oauth_access_token'],
            ], 200),
            'https://open.larksuite.com/open-apis/authen/v1/user_info' => Http::response([
                'code' => 0,
                'data' => [
                    'user_id' => 'ou_new_link_2',
                    'email' => 'email.match@example.com',
                    'name' => 'Email Match',
                ],
            ], 200),
        ]);

        $response = $this->getJson('/api/v1/lark/oauth/callback?code=oauth_code_xyz&state=test_state_2');

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'lark_user_id' => 'ou_new_link_2',
        ]);
    }

    public function test_lark_oauth_callback_alias_path_works(): void
    {
        config()->set('lark.app_id', 'cli_test_123');
        config()->set('lark.app_secret', 'secret_test_123');
        config()->set('lark.oauth.redirect_uri', 'http://localhost:5173/auth/lark/callback');

        $user = User::factory()->create([
            'email' => 'alias@example.com',
            'lark_user_id' => 'ou_alias_1',
            'status' => 'active',
        ]);

        Cache::put('lark:oauth:state:alias_state', true, now()->addMinutes(10));
        Http::fake([
            'https://open.larksuite.com/open-apis/authen/v2/oauth/token' => Http::response([
                'code' => 0,
                'data' => ['access_token' => 'oauth_access_token'],
            ], 200),
            'https://open.larksuite.com/open-apis/authen/v1/user_info' => Http::response([
                'code' => 0,
                'data' => [
                    'user_id' => 'ou_alias_1',
                    'email' => 'alias@example.com',
                    'name' => 'Alias User',
                ],
            ], 200),
        ]);

        $response = $this->getJson('/api/callback/lark?code=oauth_alias&state=alias_state');

        $response->assertStatus(200)
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonStructure(['data' => ['access_token', 'user']]);
    }

    public function test_lark_oauth_callback_accepts_post_json_body(): void
    {
        config()->set('lark.app_id', 'cli_test_123');
        config()->set('lark.app_secret', 'secret_test_123');
        config()->set('lark.oauth.redirect_uri', 'http://localhost:5173/auth/lark/callback');

        $user = User::factory()->create([
            'email' => 'post.json@example.com',
            'lark_user_id' => 'ou_post_json',
            'status' => 'active',
        ]);

        Cache::put('lark:oauth:state:post_state', true, now()->addMinutes(10));
        Http::fake([
            'https://open.larksuite.com/open-apis/authen/v2/oauth/token' => Http::response([
                'code' => 0,
                'data' => ['access_token' => 'oauth_access_token'],
            ], 200),
            'https://open.larksuite.com/open-apis/authen/v1/user_info' => Http::response([
                'code' => 0,
                'data' => [
                    'user_id' => 'ou_post_json',
                    'email' => 'post.json@example.com',
                    'name' => 'Post Json',
                ],
            ], 200),
        ]);

        $response = $this->postJson('/api/v1/lark/oauth/callback', [
            'code' => 'oauth_post_body',
            'state' => 'post_state',
        ]);

        $response->assertStatus(200)->assertJsonPath('data.user.id', $user->id);
    }

    public function test_lark_callback_auto_creates_active_staff_user_when_no_match(): void
    {
        config()->set('lark.app_id', 'cli_test_123');
        config()->set('lark.app_secret', 'secret_test_123');
        config()->set('lark.oauth.redirect_uri', 'http://localhost:5173/auth/lark/callback');

        Cache::put('lark:oauth:state:create_state', true, now()->addMinutes(10));
        Http::fake([
            'https://open.larksuite.com/open-apis/authen/v2/oauth/token' => Http::response([
                'code' => 0,
                'data' => ['access_token' => 'oauth_access_token'],
            ], 200),
            'https://open.larksuite.com/open-apis/authen/v1/user_info' => Http::response([
                'code' => 0,
                'data' => [
                    'user_id' => 'ou_new_user_1',
                    'email' => 'new.lark.user@example.com',
                    'name' => 'New Lark User',
                    'avatar_url' => 'https://img.example.com/avatar-new.png',
                ],
            ], 200),
        ]);

        $response = $this->getJson('/api/v1/lark/oauth/callback?code=oauth_create_code&state=create_state');

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonPath('data.user.email', 'new.lark.user@example.com');

        $this->assertDatabaseHas('users', [
            'email' => 'new.lark.user@example.com',
            'lark_user_id' => 'ou_new_user_1',
            'status' => 'active',
            'avatar_url' => 'https://img.example.com/avatar-new.png',
        ]);

        $created = User::where('email', 'new.lark.user@example.com')->firstOrFail();
        $this->assertTrue($created->roles()->where('name', 'staff')->exists());
    }

    public function test_lark_callback_does_not_overwrite_avatar_for_already_linked_user(): void
    {
        config()->set('lark.app_id', 'cli_test_123');
        config()->set('lark.app_secret', 'secret_test_123');
        config()->set('lark.oauth.redirect_uri', 'http://localhost:5173/auth/lark/callback');

        $user = User::factory()->create([
            'email' => 'linked.user@example.com',
            'lark_user_id' => 'ou_linked_1',
            'avatar_url' => 'https://img.example.com/original-avatar.png',
            'status' => 'active',
        ]);

        Cache::put('lark:oauth:state:linked_state', true, now()->addMinutes(10));
        Http::fake([
            'https://open.larksuite.com/open-apis/authen/v2/oauth/token' => Http::response([
                'code' => 0,
                'data' => ['access_token' => 'oauth_access_token'],
            ], 200),
            'https://open.larksuite.com/open-apis/authen/v1/user_info' => Http::response([
                'code' => 0,
                'data' => [
                    'user_id' => 'ou_linked_1',
                    'email' => 'linked.user@example.com',
                    'name' => 'Linked User',
                    'avatar_url' => 'https://img.example.com/new-avatar-from-lark.png',
                ],
            ], 200),
        ]);

        $response = $this->getJson('/api/v1/lark/oauth/callback?code=oauth_linked_code&state=linked_state');

        $response->assertStatus(200)
            ->assertJsonPath('data.user.id', $user->id);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'avatar_url' => 'https://img.example.com/original-avatar.png',
        ]);
    }

    public function test_lark_callback_returns_401_when_lark_user_id_is_already_linked_to_another_user(): void
    {
        config()->set('lark.app_id', 'cli_test_123');
        config()->set('lark.app_secret', 'secret_test_123');
        config()->set('lark.oauth.redirect_uri', 'http://localhost:5173/auth/lark/callback');

        User::factory()->create([
            'email' => 'linked.owner@example.com',
            'lark_user_id' => 'ou_conflict_1',
            'status' => 'inactive',
        ]);

        $candidate = User::factory()->create([
            'email' => 'candidate@example.com',
            'lark_user_id' => null,
            'status' => 'active',
        ]);

        Cache::put('lark:oauth:state:conflict_state', true, now()->addMinutes(10));
        Http::fake([
            'https://open.larksuite.com/open-apis/authen/v2/oauth/token' => Http::response([
                'code' => 0,
                'data' => ['access_token' => 'oauth_access_token'],
            ], 200),
            'https://open.larksuite.com/open-apis/authen/v1/user_info' => Http::response([
                'code' => 0,
                'data' => [
                    'user_id' => 'ou_conflict_1',
                    'email' => 'candidate@example.com',
                    'name' => 'Candidate User',
                ],
            ], 200),
        ]);

        $response = $this->getJson('/api/v1/lark/oauth/callback?code=oauth_conflict_code&state=conflict_state');

        $response->assertStatus(401)
            ->assertJsonPath('message', 'This Lark account is already linked to another user.');

        $this->assertDatabaseHas('users', [
            'id' => $candidate->id,
            'lark_user_id' => null,
        ]);
    }

    public function test_lark_oauth_callback_returns_401_when_lark_redirects_with_error(): void
    {
        $response = $this->getJson('/api/v1/lark/oauth/callback?error=access_denied&error_description=User+cancelled');

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
            ])
            ->assertJsonPath('message', 'Lark OAuth declined or failed: access_denied: User cancelled');
    }

    public function test_lark_callback_alias_supports_url_verification_challenge(): void
    {
        config()->set('lark.verification_token', 'verify_token_123');

        $response = $this->postJson('/api/callback/lark', [
            'type' => 'url_verification',
            'token' => 'verify_token_123',
            'challenge' => 'challenge_abc',
        ]);

        $response->assertStatus(200)
            ->assertExactJson([
                'challenge' => 'challenge_abc',
            ]);
    }

    public function test_lark_callback_alias_supports_challenge_only_payload(): void
    {
        $response = $this->postJson('/api/callback/lark', [
            'challenge' => 'challenge_only_abc',
        ]);

        $response->assertStatus(200)
            ->assertExactJson([
                'challenge' => 'challenge_only_abc',
            ]);
    }

    public function test_lark_callback_alias_rejects_url_verification_with_invalid_token(): void
    {
        config()->set('lark.verification_token', 'verify_token_123');

        $response = $this->postJson('/api/callback/lark', [
            'type' => 'url_verification',
            'token' => 'wrong_token',
            'challenge' => 'challenge_abc',
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('message', 'Invalid verification token');
    }
}
