<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkScheduleTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class WorkScheduleTemplateCrudTest extends TestCase
{
    use RefreshDatabase;

    private function actingAdmin(): User
    {
        $adminRole = Role::query()->firstOrCreate(['name' => 'admin']);
        $admin = User::factory()->create(['status' => 'active', 'driver_id' => null]);
        $admin->roles()->syncWithoutDetaching([$adminRole->id]);
        Sanctum::actingAs($admin);

        return $admin;
    }

    public function test_admin_can_crud_templates_for_tenant(): void
    {
        $company = Company::factory()->create();
        $this->actingAdmin();

        $create = $this->postJson(
            '/api/v1/work-schedule-templates',
            [
                'name' => 'Ca sáng',
                'shift_code' => 'day',
                'start_time' => '07:00',
                'end_time' => '17:00',
                'description' => 'Mô tả',
            ],
            ['X-Tenant-ID' => (string) $company->id],
        );

        $create->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Ca sáng');

        $templateId = (int) $create->json('data.id');

        $index = $this->getJson(
            '/api/v1/work-schedule-templates?company_id='.$company->id,
            ['X-Tenant-ID' => (string) $company->id],
        );
        $index->assertOk()->assertJsonPath('data.templates.0.id', $templateId);

        $show = $this->getJson(
            '/api/v1/work-schedule-templates/'.$templateId,
            ['X-Tenant-ID' => (string) $company->id],
        );
        $show->assertOk()->assertJsonPath('data.shift_code', 'day');

        $update = $this->putJson(
            '/api/v1/work-schedule-templates/'.$templateId,
            ['name' => 'Ca sáng (sửa)', 'is_active' => false],
            ['X-Tenant-ID' => (string) $company->id],
        );
        $update->assertOk()->assertJsonPath('data.name', 'Ca sáng (sửa)');

        $destroy = $this->deleteJson(
            '/api/v1/work-schedule-templates/'.$templateId,
            [],
            ['X-Tenant-ID' => (string) $company->id],
        );
        $destroy->assertOk()->assertJsonPath('success', true);

        $this->assertSoftDeleted('work_schedule_templates', ['id' => $templateId]);
    }

    public function test_show_returns_404_when_template_belongs_to_other_company(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();
        $template = WorkScheduleTemplate::query()->create([
            'company_id' => $companyA->id,
            'name' => 'A only',
            'shift_code' => 'day',
            'start_time' => '07:00:00',
            'end_time' => '17:00:00',
            'is_active' => true,
        ]);

        $this->actingAdmin();

        $response = $this->getJson(
            '/api/v1/work-schedule-templates/'.$template->id,
            ['X-Tenant-ID' => (string) $companyB->id],
        );

        $response->assertStatus(404);
    }
}
