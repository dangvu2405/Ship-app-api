<?php

namespace Tests\Feature\Api;

use App\Models\Company;
use App\Models\User;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CompaniesApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_companies_index_requires_auth(): void
    {
        $response = $this->getJson('/api/v1/companies');

        $response->assertStatus(401);
    }

    public function test_companies_index_returns_paginated_with_meta(): void
    {
        $admin = $this->getAdminUser();
        Sanctum::actingAs($admin);

        Company::factory()->count(2)->create();

        $response = $this->getJson('/api/v1/companies?per_page=10');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'OK',
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'data',
                    'meta' => [
                        'current_page',
                        'last_page',
                        'per_page',
                        'total',
                    ],
                ],
            ]);
    }

    public function test_companies_show_returns_company(): void
    {
        $admin = $this->getAdminUser();
        Sanctum::actingAs($admin);

        $company = Company::factory()->create(['code' => 'C001', 'name' => 'Test Company']);

        $response = $this->getJson('/api/v1/companies/'.$company->id);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $company->id,
                    'code' => 'C001',
                    'name' => 'Test Company',
                ],
            ]);
    }

    protected function getAdminUser()
    {
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $user = User::factory()->create(['status' => 'active']);
        $user->roles()->attach($adminRole->id);
        return $user;
    }

    public function test_admin_can_create_company(): void
    {
        $admin = $this->getAdminUser();
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/v1/companies', [
            'code' => 'COMP01',
            'name' => 'CyberLogistics',
            'address' => 'HCM',
            'status' => 'active',
        ]);

        $response->assertStatus(201)
            ->assertJsonFragment(['name' => 'CyberLogistics']);
        
        $this->assertDatabaseHas('companies', ['code' => 'COMP01']);
    }

    public function test_admin_can_update_company(): void
    {
        $admin = $this->getAdminUser();
        Sanctum::actingAs($admin);
        
        $company = Company::factory()->create(['name' => 'Old Name']);

        $response = $this->putJson('/api/v1/companies/' . $company->id, [
            'name' => 'New Name',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('companies', ['id' => $company->id, 'name' => 'New Name']);
    }

    public function test_admin_can_delete_company(): void
    {
        $admin = $this->getAdminUser();
        Sanctum::actingAs($admin);

        $company = Company::factory()->create();

        $response = $this->deleteJson('/api/v1/companies/' . $company->id);

        $response->assertStatus(200);
        $this->assertSoftDeleted('companies', ['id' => $company->id]);
    }
}
