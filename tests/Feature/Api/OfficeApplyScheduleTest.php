<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Jobs\ApplyOfficeScheduleJob;
use App\Models\Company;
use App\Models\Driver;
use App\Models\Office;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkScheduleTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class OfficeApplyScheduleTest extends TestCase
{
    use RefreshDatabase;

    public function test_apply_schedule_bulk_inserts_drafts_for_all_office_drivers(): void
    {
        $company = Company::factory()->create();
        $office = Office::factory()->create(['company_id' => $company->id]);
        $template = WorkScheduleTemplate::query()->create([
            'company_id' => $company->id,
            'name' => 'Ca ngày chuẩn',
            'shift_code' => 'day',
            'start_time' => '07:00:00',
            'end_time' => '17:00:00',
            'is_active' => true,
        ]);

        Driver::factory()->count(3)->create([
            'office_id' => $office->id,
            'company_id' => $company->id,
            'status' => 'active',
        ]);

        $adminRole = Role::query()->firstOrCreate(['name' => 'admin']);
        $admin = User::factory()->create(['status' => 'active', 'driver_id' => null]);
        $admin->roles()->syncWithoutDetaching([$adminRole->id]);

        Sanctum::actingAs($admin);

        $response = $this->postJson(
            '/api/v1/offices/'.$office->id.'/apply-schedule',
            [
                'schedule_id' => $template->id,
                'start_date' => '2026-05-01',
                'end_date' => '2026-05-03',
                'replace_drafts' => true,
            ],
            ['X-Tenant-ID' => (string) $company->id],
        );

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.rows_created', 9);

        $this->assertSame(9, (int) \Illuminate\Support\Facades\DB::table('driver_work_schedules')->count());
    }

    public function test_apply_schedule_dispatches_job_when_estimated_rows_exceed_sync_max(): void
    {
        config(['ship.office_schedule_apply.sync_max_rows' => 5]);
        Bus::fake();

        $company = Company::factory()->create();
        $office = Office::factory()->create(['company_id' => $company->id]);
        $template = WorkScheduleTemplate::query()->create([
            'company_id' => $company->id,
            'name' => 'Ca ngày chuẩn',
            'shift_code' => 'day',
            'start_time' => '07:00:00',
            'end_time' => '17:00:00',
            'is_active' => true,
        ]);

        Driver::factory()->count(3)->create([
            'office_id' => $office->id,
            'company_id' => $company->id,
            'status' => 'active',
        ]);

        $adminRole = Role::query()->firstOrCreate(['name' => 'admin']);
        $admin = User::factory()->create(['status' => 'active', 'driver_id' => null]);
        $admin->roles()->syncWithoutDetaching([$adminRole->id]);

        Sanctum::actingAs($admin);

        $response = $this->postJson(
            '/api/v1/offices/'.$office->id.'/apply-schedule',
            [
                'schedule_id' => $template->id,
                'start_date' => '2026-05-01',
                'end_date' => '2026-05-03',
                'replace_drafts' => true,
            ],
            ['X-Tenant-ID' => (string) $company->id],
        );

        $response->assertStatus(202)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.queued', true)
            ->assertJsonPath('data.estimated_rows', 9)
            ->assertJsonPath('data.sync_max_rows', 5);

        Bus::assertDispatched(ApplyOfficeScheduleJob::class, function (ApplyOfficeScheduleJob $job) use ($office, $template, $admin): bool {
            return $job->officeId === $office->id
                && $job->templateId === $template->id
                && $job->actorUserId === $admin->id;
        });

        $this->assertSame(0, (int) \Illuminate\Support\Facades\DB::table('driver_work_schedules')->count());
    }
}
