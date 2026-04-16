<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\Role;
use App\Models\Office;
use App\Models\Department;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\DB;
use Illuminate\Auth\Notifications\ResetPassword;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    private const SOCIAL_LOGIN_ENDPOINT = '/api/v1/auth/social/login';
    private const LARK_AUTH_CONTROLLER = 'App\\Http\\Controllers\\Api\\LarkAuthController';

    protected function setUp(): void
    {
        parent::setUp();

        if (str_starts_with($this->name(), 'test_lark_') && ! class_exists(self::LARK_AUTH_CONTROLLER)) {
            $this->markTestSkipped('Lark auth controller is not available in this build.');
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function postSocialLogin(array $payload): \Illuminate\Testing\TestResponse
    {
        return $this->postJson(self::SOCIAL_LOGIN_ENDPOINT, $payload);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{token: string, x5c: string, kid: string}
     */
    private function createAppleSignedIdToken(array $payload): array
    {
        $privateKey = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'private_key_bits' => 2048,
        ]);

        if ($privateKey === false) {
            self::fail('Failed to create private key for Apple token test.');
        }

        $csr = openssl_csr_new(['commonName' => 'appleid.apple.com'], $privateKey, ['digest_alg' => 'sha256']);
        if ($csr === false) {
            self::fail('Failed to create CSR for Apple token test.');
        }

        $x509 = openssl_csr_sign($csr, null, $privateKey, 1, ['digest_alg' => 'sha256']);
        if ($x509 === false) {
            self::fail('Failed to sign certificate for Apple token test.');
        }

        $header = [
            'alg' => 'RS256',
            'typ' => 'JWT',
            'kid' => 'test-kid-apple',
        ];

        $encodedHeader = rtrim(strtr(base64_encode((string) json_encode($header)), '+/', '-_'), '=');
        $encodedPayload = rtrim(strtr(base64_encode((string) json_encode($payload)), '+/', '-_'), '=');
        $signingInput = $encodedHeader.'.'.$encodedPayload;

        $signature = '';
        $isSigned = openssl_sign($signingInput, $signature, $privateKey, OPENSSL_ALGO_SHA256);
        if (! $isSigned) {
            self::fail('Failed to sign Apple token test payload.');
        }

        $encodedSignature = rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');
        $token = $signingInput.'.'.$encodedSignature;

        $certPem = '';
        $isExported = openssl_x509_export($x509, $certPem);
        if (! $isExported) {
            self::fail('Failed to export cert for Apple token test.');
        }

        openssl_pkey_free($privateKey);

        $x5c = str_replace(
            ["-----BEGIN CERTIFICATE-----\n", "-----END CERTIFICATE-----\n", "\n", "\r"],
            '',
            $certPem
        );

        return [
            'token' => $token,
            'x5c' => $x5c,
            'kid' => 'test-kid-apple',
        ];
    }

    public function test_email_password_login_works_with_v1_prefix(): void
    {
        $user = User::factory()->create([
            'email' => 'login.v1@example.com',
            'password' => Hash::make('password123'),
            'status' => 'active',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'login.v1@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Login successful',
            ])
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonStructure([
                'data' => ['user', 'token', 'refreshToken'],
            ]);

        $this->assertDatabaseHas('refresh_tokens', [
            'user_id' => $user->id,
            'is_revoked' => false,
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

    public function test_social_login_with_google_id_token_logs_in_or_creates_user(): void
    {
        config()->set('services.google.client_id', 'google-client-id-123');

        Http::fake([
            'https://oauth2.googleapis.com/tokeninfo*' => Http::response([
                'sub' => 'google_user_1',
                'email' => 'google.user@example.com',
                'name' => 'Google User',
                'picture' => 'https://example.com/google.png',
                'iss' => 'https://accounts.google.com',
                'aud' => 'google-client-id-123',
                'email_verified' => true,
            ], 200),
        ]);

        $response = $this->postSocialLogin([
            'provider' => 'google',
            'id_token' => 'google-id-token',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.user.email', 'google.user@example.com')
            ->assertJsonStructure([
                'success',
                'message',
                'data' => ['user', 'token'],
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'google.user@example.com',
            'social_provider' => 'google',
            'social_provider_id' => 'google_user_1',
        ]);
    }

    public function test_social_login_with_facebook_access_token_logs_in_or_creates_user(): void
    {
        Http::fake([
            'https://graph.facebook.com/me*' => Http::response([
                'id' => 'fb_user_1',
                'email' => 'facebook.user@example.com',
                'name' => 'Facebook User',
                'verified' => true,
                'picture' => [
                    'data' => ['url' => 'https://example.com/facebook.png'],
                ],
            ], 200),
        ]);

        $response = $this->postSocialLogin([
            'provider' => 'facebook',
            'access_token' => 'facebook-access-token',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.user.email', 'facebook.user@example.com')
            ->assertJsonStructure([
                'success',
                'message',
                'data' => ['user', 'token'],
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'facebook.user@example.com',
            'social_provider' => 'facebook',
            'social_provider_id' => 'fb_user_1',
        ]);
    }

    public function test_social_login_with_apple_id_token_logs_in_or_creates_user(): void
    {
        config()->set('services.apple.client_id', 'com.ship.app');

        $appleToken = $this->createAppleSignedIdToken([
            'iss' => 'https://appleid.apple.com',
            'sub' => 'apple_user_1',
            'email' => 'apple.user@example.com',
            'email_verified' => true,
            'aud' => 'com.ship.app',
            'exp' => now()->addMinutes(10)->timestamp,
        ]);

        Http::fake([
            'https://appleid.apple.com/auth/keys' => Http::response([
                'keys' => [[
                    'kid' => $appleToken['kid'],
                    'x5c' => [$appleToken['x5c']],
                ]],
            ], 200),
        ]);

        $response = $this->postSocialLogin([
            'provider' => 'apple',
            'id_token' => $appleToken['token'],
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.user.email', 'apple.user@example.com')
            ->assertJsonStructure([
                'success',
                'message',
                'data' => ['user', 'token'],
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'apple.user@example.com',
            'social_provider' => 'apple',
            'social_provider_id' => 'apple_user_1',
        ]);
    }

    public function test_social_login_rejects_google_token_with_wrong_audience(): void
    {
        config()->set('services.google.client_id', 'google-client-id-123');

        Http::fake([
            'https://oauth2.googleapis.com/tokeninfo*' => Http::response([
                'sub' => 'google_user_1',
                'email' => 'google.user@example.com',
                'iss' => 'https://accounts.google.com',
                'aud' => 'another-client-id',
                'email_verified' => true,
            ], 200),
        ]);

        $response = $this->postSocialLogin([
            'provider' => 'google',
            'id_token' => 'google-id-token',
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('message', 'Google token audience mismatch');
    }

    public function test_social_login_rejects_apple_token_with_invalid_signature(): void
    {
        config()->set('services.apple.client_id', 'com.ship.app');

        $appleToken = $this->createAppleSignedIdToken([
            'iss' => 'https://appleid.apple.com',
            'sub' => 'apple_user_invalid_sig',
            'email' => 'apple.invalid@example.com',
            'email_verified' => true,
            'aud' => 'com.ship.app',
            'exp' => now()->addMinutes(10)->timestamp,
        ]);

        Http::fake([
            'https://appleid.apple.com/auth/keys' => Http::response([
                'keys' => [[
                    'kid' => 'another-kid',
                    'x5c' => [$appleToken['x5c']],
                ]],
            ], 200),
        ]);

        $response = $this->postSocialLogin([
            'provider' => 'apple',
            'id_token' => $appleToken['token'],
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('message', 'Invalid Apple token signature');
    }

    public function test_social_login_rejects_inactive_user(): void
    {
        User::factory()->create([
            'email' => 'inactive.google@example.com',
            'status' => 'inactive',
        ]);

        config()->set('services.google.client_id', 'google-client-id-123');

        Http::fake([
            'https://oauth2.googleapis.com/tokeninfo*' => Http::response([
                'sub' => 'google_user_inactive',
                'email' => 'inactive.google@example.com',
                'name' => 'Inactive Google User',
                'iss' => 'https://accounts.google.com',
                'aud' => 'google-client-id-123',
                'email_verified' => true,
            ], 200),
        ]);

        $response = $this->postSocialLogin([
            'provider' => 'google',
            'id_token' => 'google-id-token-inactive',
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('message', 'Your account is inactive');
    }

    public function test_social_login_rejects_unverified_provider_email_for_existing_account(): void
    {
        User::factory()->create([
            'email' => 'existing.user@example.com',
            'status' => 'active',
        ]);

        config()->set('services.google.client_id', 'google-client-id-123');

        Http::fake([
            'https://oauth2.googleapis.com/tokeninfo*' => Http::response([
                'sub' => 'google_user_unverified',
                'email' => 'existing.user@example.com',
                'name' => 'Existing User',
                'iss' => 'https://accounts.google.com',
                'aud' => 'google-client-id-123',
                'email_verified' => false,
            ], 200),
        ]);

        $response = $this->postSocialLogin([
            'provider' => 'google',
            'id_token' => 'google-id-token-unverified',
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('message', 'Provider email is not verified');
    }

    public function test_forgot_password_sends_reset_link_for_existing_user(): void
    {
        Notification::fake();
        $user = User::factory()->create([
            'email' => 'forgot@example.com',
            'status' => 'active',
        ]);

        $response = $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'forgot@example.com',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Password reset link sent',
            ]);

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_reset_password_updates_password_with_valid_token(): void
    {
        $user = User::factory()->create([
            'email' => 'reset@example.com',
            'status' => 'active',
        ]);

        $token = Password::getRepository()->create($user);

        $response = $this->postJson('/api/v1/auth/reset-password', [
            'email' => 'reset@example.com',
            'token' => $token,
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Password reset successful',
            ]);

        $user->refresh();
        $this->assertTrue(Hash::check('new-password-123', $user->password));
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
        $this->assertSame($me->json('data.user.email'), $legacy->json('data.user.email'));
    }

    public function test_auth_actions_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/auth/actions');

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_admin_can_filter_auth_actions_by_filters(): void
    {
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $admin = User::factory()->create(['status' => 'active', 'username' => 'root-admin']);
        $admin->roles()->attach($adminRole->id);

        $targetUser = User::factory()->create(['status' => 'active', 'username' => 'driver.target']);
        $otherUser = User::factory()->create(['status' => 'active', 'username' => 'driver.other']);

        DB::table('audit_logs')->insert([
            [
                'user_id' => $targetUser->id,
                'company_id' => null,
                'action' => 'POST /api/v1/trips',
                'table_name' => 'api_requests',
                'resource' => '/api/v1/trips',
                'record_id' => null,
                'old_data' => null,
                'new_data' => json_encode(['status_code' => 201]),
                'metadata' => null,
                'ip_address' => '127.0.0.1',
                'request_id' => 'req-1',
                'user_agent' => 'PHPUnit',
                'created_at' => now()->subMinutes(10),
                'updated_at' => now()->subMinutes(10),
            ],
            [
                'user_id' => $otherUser->id,
                'company_id' => null,
                'action' => 'GET /api/v1/trips',
                'table_name' => 'api_requests',
                'resource' => '/api/v1/trips',
                'record_id' => null,
                'old_data' => null,
                'new_data' => json_encode(['status_code' => 200]),
                'metadata' => null,
                'ip_address' => '127.0.0.1',
                'request_id' => 'req-2',
                'user_agent' => 'PHPUnit',
                'created_at' => now()->subMinutes(5),
                'updated_at' => now()->subMinutes(5),
            ],
        ]);

        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/v1/auth/actions?username=driver.target&action=POST&from='.urlencode(now()->subDay()->toDateTimeString()).'&to='.urlencode(now()->toDateTimeString()).'&status_code=201');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Auth actions retrieved')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.username', 'driver.target')
            ->assertJsonPath('data.0.action', 'POST /api/v1/trips')
            ->assertJsonPath('data.0.statusCode', 201)
            ->assertJsonStructure([
                'data' => [[
                    'id',
                    'username',
                    'action',
                    'resource',
                    'tableName',
                    'recordId',
                    'entityType',
                    'statusCode',
                    'performedBy',
                    'createdAt',
                ]],
            ]);
    }

    public function test_auth_actions_capture_crud_and_other_authenticated_actions(): void
    {
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $admin = User::factory()->create(['status' => 'active', 'username' => 'audit.admin']);
        $admin->roles()->attach($adminRole->id);

        $this->actingAs($admin, 'sanctum');

        $office = Office::factory()->create();

        $create = $this->postJson('/api/v1/departments', [
            'office_id' => $office->id,
            'code' => 'DEP-AUDIT-01',
            'name' => 'Audit Department',
        ]);
        $create->assertStatus(201);

        $departmentId = (int) Department::query()
            ->where('code', 'DEP-AUDIT-01')
            ->value('id');
        $this->assertNotSame(0, $departmentId);

        $this->putJson('/api/v1/departments/'.$departmentId, [
            'name' => 'Audit Department Updated',
        ])->assertStatus(200);

        $this->deleteJson('/api/v1/departments/'.$departmentId)->assertStatus(200);
        $this->postJson('/api/v1/auth/refresh')->assertStatus(200);

        $response = $this->getJson('/api/v1/auth/actions?username=audit.admin');
        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Auth actions retrieved')
            ->assertJsonStructure([
                'data' => [[
                    'id',
                    'username',
                    'action',
                    'resource',
                    'tableName',
                    'recordId',
                    'entityType',
                    'statusCode',
                    'performedBy',
                    'createdAt',
                ]],
            ]);

        $actions = collect($response->json('data'))->pluck('action')->all();
        $this->assertContains('POST /api/v1/departments', $actions);
        $this->assertContains('PUT /api/v1/departments/'.$departmentId, $actions);
        $this->assertContains('DELETE /api/v1/departments/'.$departmentId, $actions);
        $this->assertContains('POST /api/v1/auth/refresh', $actions);

        $deleteAction = collect($response->json('data'))
            ->firstWhere('action', 'DELETE /api/v1/departments/'.$departmentId);
        $this->assertIsArray($deleteAction);
        $this->assertSame('departments', $deleteAction['tableName']);
        $this->assertSame($departmentId, $deleteAction['recordId']);
        $this->assertSame('department', $deleteAction['entityType']);
    }

    public function test_auth_actions_date_range_with_yyyy_mm_dd_is_inclusive_for_whole_day(): void
    {
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $admin = User::factory()->create(['status' => 'active', 'username' => 'admin']);
        $admin->roles()->attach($adminRole->id);

        DB::table('audit_logs')->insert([
            'user_id' => $admin->id,
            'company_id' => null,
            'action' => 'DELETE /api/v1/departments/10',
            'table_name' => 'api_requests',
            'resource' => '/api/v1/departments/10',
            'record_id' => null,
            'old_data' => null,
            'new_data' => json_encode(['status_code' => 200]),
            'metadata' => null,
            'ip_address' => '127.0.0.1',
            'request_id' => 'req-delete-day',
            'user_agent' => 'PHPUnit',
            'created_at' => '2026-04-14 15:30:00',
            'updated_at' => '2026-04-14 15:30:00',
        ]);

        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/v1/auth/actions?username=admin&action=DELETE&from=2026-04-14&to=2026-04-14&status_code=200');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Auth actions retrieved')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.action', 'DELETE /api/v1/departments/10')
            ->assertJsonPath('data.0.statusCode', 200);
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
                    'refreshToken',
                ],
            ]);

        $this->assertCount(1, $user->tokens);
        $this->assertStringNotContainsString(explode('|', $token)[1] ?? '', $response->json('data.token'));
        $this->assertDatabaseHas('refresh_tokens', [
            'user_id' => $user->id,
            'is_revoked' => false,
        ]);
    }

    public function test_refresh_rotates_refresh_token_when_logged_in_token_was_issued_by_login(): void
    {
        $password = 'password123';
        User::factory()->create([
            'email' => 'rotate-refresh@example.com',
            'password' => Hash::make($password),
            'status' => 'active',
        ]);

        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => 'rotate-refresh@example.com',
            'password' => $password,
        ]);

        $loginResponse->assertStatus(200);
        $accessToken = (string) $loginResponse->json('data.token');

        $refreshResponse = $this->withHeaders([
            'Authorization' => 'Bearer '.$accessToken,
        ])->postJson('/api/v1/auth/refresh');

        $refreshResponse->assertStatus(200)
            ->assertJsonStructure([
                'data' => ['token', 'refreshToken'],
            ]);

        $userId = (int) User::query()->where('email', 'rotate-refresh@example.com')->value('id');
        $activeRefreshTokenCount = DB::table('refresh_tokens')
            ->where('user_id', $userId)
            ->where('is_revoked', false)
            ->count();
        $this->assertSame(1, $activeRefreshTokenCount);
    }

    public function test_can_refresh_with_refresh_token_without_access_token(): void
    {
        $password = 'password123';
        User::factory()->create([
            'email' => 'refresh.by.token@example.com',
            'password' => Hash::make($password),
            'status' => 'active',
        ]);

        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => 'refresh.by.token@example.com',
            'password' => $password,
        ]);
        $loginResponse->assertStatus(200);
        $refreshToken = (string) $loginResponse->json('data.refreshToken');

        $refreshResponse = $this->postJson('/api/v1/auth/refresh-token', [
            'refresh_token' => $refreshToken,
        ]);

        $refreshResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Token refreshed successfully')
            ->assertJsonStructure([
                'data' => ['token', 'refreshToken'],
            ]);
    }

    public function test_refresh_with_invalid_refresh_token_returns_401(): void
    {
        $response = $this->postJson('/api/v1/auth/refresh-token', [
            'refresh_token' => str_repeat('a', 64),
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Invalid or expired refresh token');
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
