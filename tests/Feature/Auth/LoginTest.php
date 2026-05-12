<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_with_valid_credentials_returns_token(): void
    {
        $user = User::factory()->create([
            'email'    => 'driver@ceta.test',
            'password' => bcrypt('secret123'),
            'status'   => 'active',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email'    => 'driver@ceta.test',
            'password' => 'secret123',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['token']]);
    }

    public function test_login_with_wrong_password_returns_401(): void
    {
        User::factory()->create([
            'email'    => 'driver2@ceta.test',
            'password' => bcrypt('correct'),
            'status'   => 'active',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email'    => 'driver2@ceta.test',
            'password' => 'wrong',
        ]);

        $response->assertUnauthorized();
    }

    public function test_login_with_nonexistent_email_returns_401(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email'    => 'nobody@ceta.test',
            'password' => 'password',
        ]);

        $response->assertUnauthorized();
    }

    public function test_login_with_missing_email_returns_422(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'password' => 'password',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_login_with_missing_password_returns_422(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'test@ceta.test',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    }

    public function test_login_with_invalid_email_format_returns_422(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email'    => 'not-an-email',
            'password' => 'password',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_me_endpoint_returns_authenticated_user(): void
    {
        // me is GET /api/auth/me (behind auth:sanctum + tenant.context)
        // setUpTenant has already called Sanctum::actingAs and withHeader(X-Tenant-ID)
        $response = $this->getJson('/api/auth/me');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['user', 'permissions', 'tenants']]);
    }

    public function test_me_endpoint_returns_401_when_unauthenticated(): void
    {
        $this->app['auth']->forgetGuards();

        $response = $this->getJson('/api/auth/me');

        $response->assertUnauthorized();
    }

    public function test_logout_returns_success(): void
    {
        $response = $this->postJson('/api/auth/logout');

        $response->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_change_password_requires_current_password(): void
    {
        $response = $this->patchJson('/api/auth/password', [
            'new_password'              => 'newpass123',
            'new_password_confirmation' => 'newpass123',
        ]);

        $response->assertUnprocessable();
    }
}
