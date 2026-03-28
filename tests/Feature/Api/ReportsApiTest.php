<?php

namespace Tests\Feature\Api;

use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReportsApiTest extends TestCase
{
    use RefreshDatabase;

    protected function getAdminUser()
    {
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $user = User::factory()->create(['status' => 'active']);
        $user->roles()->attach($adminRole->id);
        return $user;
    }

    public function test_dashboard_report_returns_data(): void
    {
        $admin = $this->getAdminUser();
        Sanctum::actingAs($admin);

        Company::factory()->count(3)->create();

        $response = $this->getJson('/api/reports/dashboard?month=3&year=2026');

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'companies_count',
                    'payrolls_count',
                ],
            ]);
    }

    public function test_payroll_summary_report_returns_data(): void
    {
        $admin = $this->getAdminUser();
        Sanctum::actingAs($admin);

        $company = Company::factory()->create();

        $response = $this->getJson('/api/reports/payroll-summary?company_id=' . $company->id . '&month=3&year=2026');

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    public function test_dashboard_requires_admin(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/reports/dashboard');

        $response->assertStatus(403);
    }
}
