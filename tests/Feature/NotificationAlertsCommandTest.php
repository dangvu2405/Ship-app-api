<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CostApprovalRequest;
use App\Models\Driver;
use App\Models\DriverDocument;
use App\Models\Office;
use App\Models\Role;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class NotificationAlertsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_t23_notifications_command_dispatches_document_and_cost_approval_alerts(): void
    {
        $company = Company::factory()->create();
        $office = Office::factory()->create(['company_id' => $company->id]);
        $driver = Driver::factory()->create(['company_id' => $company->id, 'office_id' => $office->id]);
        $vehicle = Vehicle::factory()->create(['company_id' => $company->id, 'office_id' => $office->id]);
        $trip = Trip::factory()->create(['company_id' => $company->id, 'driver_id' => $driver->id, 'vehicle_id' => $vehicle->id]);

        $role = Role::query()->firstOrCreate(['name' => 'admin']);
        $admin = User::factory()->create(['status' => 'active']);
        $admin->roles()->syncWithoutDetaching([$role->id]);

        DriverDocument::query()->create([
            'company_id' => $company->id,
            'driver_id' => $driver->id,
            'doc_type' => 'driver_license',
            'doc_name' => 'DL',
            'issued_date' => now()->subYear()->toDateString(),
            'expiry_date' => now()->addDays(3)->toDateString(),
            'file_url' => 'https://cdn.example.com/dl.pdf',
        ]);

        VehicleDocument::query()->create([
            'company_id' => $company->id,
            'vehicle_id' => $vehicle->id,
            'doc_type' => 'inspection',
            'doc_name' => 'Inspect',
            'issued_date' => now()->subYear()->toDateString(),
            'expiry_date' => now()->addDays(4)->toDateString(),
            'file_url' => 'https://cdn.example.com/inspection.pdf',
        ]);

        CostApprovalRequest::query()->create([
            'company_id' => $company->id,
            'trip_id' => $trip->id,
            'requested_by' => $admin->id,
            'total_amount' => 500000,
            'reason' => 'Over threshold',
            'status' => 'pending',
        ]);

        $this->artisan('alerts:dispatch-notifications')->assertExitCode(0);

        $this->assertGreaterThanOrEqual(3, $admin->fresh()->notifications()->count());
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $admin->id]);
    }
}
