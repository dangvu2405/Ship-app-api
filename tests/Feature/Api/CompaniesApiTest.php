<?php

namespace Tests\Feature\Api;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CompaniesApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_companies_index_requires_auth(): void
    {
        $response = $this->getJson('/api/companies');

        $response->assertStatus(401);
    }

    public function test_companies_index_returns_paginated_with_meta(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        Company::factory()->count(2)->create();

        $response = $this->getJson('/api/companies?per_page=10');

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
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $company = Company::factory()->create(['code' => 'C001', 'name' => 'Test Company']);

        $response = $this->getJson('/api/companies/'.$company->id);

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
}
