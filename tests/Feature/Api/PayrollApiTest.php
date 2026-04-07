<?php

namespace Tests\Feature\Api;

use App\Models\Payroll;
use App\Models\User;
use App\Models\Role;
use App\Models\Company;
use App\Models\PayrollPeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollApiTest extends TestCase
{
    use RefreshDatabase;

    protected function getAdminUser()
    {
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $user = User::factory()->create(['status' => 'active']);
        $user->roles()->attach($adminRole->id);
        return $user;
    }

    protected function createPeriod($company)
    {
        return PayrollPeriod::create([
            'company_id' => $company->id,
            'code' => 'P' . uniqid(),
            'period_type' => 'monthly',
            'start_date' => '2026-05-01',
            'end_date' => '2026-05-31',
            'status' => 'open'
        ]);
    }

    public function test_can_approve_payroll(): void
    {
        $admin = $this->getAdminUser();
        $company = Company::factory()->create();
        $period = $this->createPeriod($company);
        
        $payroll = Payroll::factory()->create([
            'company_id' => $company->id,
            'payroll_period_id' => $period->id,
            'status' => 'draft',
            'month' => 5,
            'year' => 2026,
        ]);

        $response = $this->actingAs($admin, 'sanctum')->postJson("/api/payrolls/{$payroll->id}/approve");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Payroll approved successfully',
            ]);

        $this->assertDatabaseHas('payrolls', [
            'id' => $payroll->id,
            'status' => 'approved',
        ]);
    }

    public function test_can_lock_payroll(): void
    {
        $admin = $this->getAdminUser();
        $company = Company::factory()->create();
        $period = $this->createPeriod($company);
        
        $payroll = Payroll::factory()->create([
            'company_id' => $company->id,
            'payroll_period_id' => $period->id,
            'status' => 'approved',
            'month' => 5,
            'year' => 2026,
        ]);

        $response = $this->actingAs($admin, 'sanctum')->postJson("/api/payrolls/{$payroll->id}/lock");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Payroll locked successfully',
            ]);

        $this->assertDatabaseHas('payrolls', [
            'id' => $payroll->id,
            'status' => 'locked',
        ]);
        $this->assertNotNull(Payroll::find($payroll->id)->locked_at);
    }

    public function test_cannot_approve_non_draft_payroll(): void
    {
        $admin = $this->getAdminUser();
        $company = Company::factory()->create();
        $period = $this->createPeriod($company);

        $payroll = Payroll::factory()->create([
            'company_id' => $company->id,
            'payroll_period_id' => $period->id,
            'status' => 'approved',
            'month' => 5,
            'year' => 2026,
        ]);

        $response = $this->actingAs($admin, 'sanctum')->postJson("/api/payrolls/{$payroll->id}/approve");

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_cannot_lock_non_approved_payroll(): void
    {
        $admin = $this->getAdminUser();
        $company = Company::factory()->create();
        $period = $this->createPeriod($company);

        $payroll = Payroll::factory()->create([
            'company_id' => $company->id,
            'payroll_period_id' => $period->id,
            'status' => 'draft',
            'month' => 5,
            'year' => 2026,
        ]);

        $response = $this->actingAs($admin, 'sanctum')->postJson("/api/payrolls/{$payroll->id}/lock");

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_cannot_update_locked_payroll(): void
    {
        $admin = $this->getAdminUser();
        $company = Company::factory()->create();
        $period = $this->createPeriod($company);

        $payroll = Payroll::factory()->create([
            'company_id' => $company->id,
            'payroll_period_id' => $period->id,
            'status' => 'locked',
            'month' => 5,
            'year' => 2026,
            'locked_at' => now(),
        ]);

        $response = $this->actingAs($admin, 'sanctum')->putJson("/api/payrolls/{$payroll->id}", [
            'status' => 'approved',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_cannot_delete_locked_payroll(): void
    {
        $admin = $this->getAdminUser();
        $company = Company::factory()->create();
        $period = $this->createPeriod($company);
        
        $payroll = Payroll::factory()->create([
            'company_id' => $company->id,
            'payroll_period_id' => $period->id,
            'status' => 'locked',
            'month' => 5,
            'year' => 2026,
            'locked_at' => now(),
        ]);

        $response = $this->actingAs($admin, 'sanctum')->deleteJson("/api/payrolls/{$payroll->id}");

        // In a real strict implementation, you return 400 Bad Request or 403 Forbidden
        // Here we test if the validation constraint kicks in protecting the record.
        $response->assertStatus(422);

        // Record should remain
        $this->assertDatabaseHas('payrolls', [
            'id' => $payroll->id,
            'status' => 'locked'
        ]);
    }

    public function test_can_delete_draft_payroll(): void
    {
        $admin = $this->getAdminUser();
        $company = Company::factory()->create();
        $period = $this->createPeriod($company);
        
        $payroll = Payroll::factory()->create([
            'company_id' => $company->id,
            'payroll_period_id' => $period->id,
            'status' => 'draft',
            'month' => 5,
            'year' => 2026,
        ]);

        $response = $this->actingAs($admin, 'sanctum')->deleteJson("/api/payrolls/{$payroll->id}");

        $response->assertStatus(200);

        $this->assertSoftDeleted('payrolls', [
            'id' => $payroll->id,
        ]);
    }

    public function test_can_export_payroll(): void
    {
        $admin = $this->getAdminUser();
        $company = Company::factory()->create();
        $period = $this->createPeriod($company);
        
        $payroll = Payroll::factory()->create([
            'company_id' => $company->id,
            'payroll_period_id' => $period->id,
            'status' => 'locked',
            'month' => 5,
            'year' => 2026,
        ]);

        $response = $this->actingAs($admin, 'sanctum')->getJson("/api/payrolls/{$payroll->id}/export");

        $response->assertStatus(200);
    }

    public function test_user_can_view_my_salary(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        
        $response = $this->actingAs($user, 'sanctum')->getJson("/api/payrolls/my-salary");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);
    }
}
