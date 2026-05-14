<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Driver;
use App\Models\DriverWorkSchedule;
use App\Models\LeaveType;
use App\Models\Trip;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class WorkflowGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_assign_trip_successfully_sets_driver_vehicle_and_status(): void
    {
        $trip = $this->makeTrip('pending', '2026-05-20');
        $driver = Driver::factory()->create([
            'company_id' => $this->company->id,
            'status' => 'active',
            'available_status' => 'available',
        ]);
        $vehicle = Vehicle::factory()->create([
            'company_id' => $this->company->id,
            'status' => 'active',
        ]);

        $this->patchJson("/api/trips/{$trip->id}/assign", [
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicle->id,
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'assigned')
            ->assertJsonPath('data.driver_id', $driver->id)
            ->assertJsonPath('data.vehicle_id', $vehicle->id);
    }

    public function test_assign_trip_rejects_vehicle_in_maintenance(): void
    {
        $trip = $this->makeTrip('pending', '2026-05-21');
        $driver = Driver::factory()->create([
            'company_id' => $this->company->id,
            'status' => 'active',
        ]);
        $vehicle = Vehicle::factory()->create([
            'company_id' => $this->company->id,
            'status' => 'maintenance',
        ]);

        $this->patchJson("/api/trips/{$trip->id}/assign", [
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicle->id,
        ])
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors(['vehicle_id']);
    }

    public function test_assign_trip_rejects_vehicle_and_driver_double_booking(): void
    {
        $driver = Driver::factory()->create([
            'company_id' => $this->company->id,
            'status' => 'active',
        ]);
        $vehicle = Vehicle::factory()->create([
            'company_id' => $this->company->id,
            'status' => 'active',
        ]);
        $existing = $this->makeTrip('assigned', '2026-05-22');
        $existing->update([
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicle->id,
        ]);

        $trip = $this->makeTrip('pending', '2026-05-22');

        $this->patchJson("/api/trips/{$trip->id}/assign", [
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicle->id,
        ])
            ->assertUnprocessable()
            ->assertJsonPath('success', false);
    }

    public function test_driver_work_schedule_rejects_duplicate_driver_same_day_shift(): void
    {
        $driver = Driver::factory()->create([
            'company_id' => $this->company->id,
            'status' => 'active',
        ]);
        $payload = [
            'driver_id' => $driver->id,
            'work_date' => '2026-05-23',
            'shift_code' => 'day',
            'start_time' => '08:00',
            'end_time' => '17:00',
        ];

        $this->postJson('/api/driver-work-schedules', $payload)
            ->assertCreated()
            ->assertJsonPath('success', true);

        $this->postJson('/api/driver-work-schedules', $payload)
            ->assertUnprocessable()
            ->assertJsonPath('success', false);
    }

    public function test_maintenance_record_validates_non_negative_cost(): void
    {
        $vehicle = Vehicle::factory()->create([
            'company_id' => $this->company->id,
            'status' => 'active',
        ]);

        $this->postJson('/api/maintenance-records', [
            'vehicle_id' => $vehicle->id,
            'type' => 'unscheduled',
            'title' => 'Brake inspection',
            'started_date' => '2026-05-24',
            'total_cost' => -1,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['total_cost']);
    }

    public function test_available_resources_exclude_busy_or_on_leave_records_for_date(): void
    {
        $busyDriver = Driver::factory()->create([
            'company_id' => $this->company->id,
            'status' => 'active',
            'available_status' => 'available',
        ]);
        $availableDriver = Driver::factory()->create([
            'company_id' => $this->company->id,
            'status' => 'active',
            'available_status' => 'available',
        ]);
        $leaveDriver = Driver::factory()->create([
            'company_id' => $this->company->id,
            'status' => 'active',
            'available_status' => 'available',
        ]);
        $busyVehicle = Vehicle::factory()->create([
            'company_id' => $this->company->id,
            'status' => 'active',
        ]);
        $availableVehicle = Vehicle::factory()->create([
            'company_id' => $this->company->id,
            'status' => 'active',
        ]);

        $trip = $this->makeTrip('assigned', '2026-05-25');
        $trip->update([
            'driver_id' => $busyDriver->id,
            'vehicle_id' => $busyVehicle->id,
        ]);

        $leaveType = LeaveType::query()->create([
            'code' => 'ANNUAL',
            'name' => 'Annual leave',
            'status' => 'active',
        ]);

        DB::table('leave_requests')->insert([
            'company_id' => $this->company->id,
            'driver_id' => $leaveDriver->id,
            'leave_type_id' => $leaveType->id,
            'from_date' => '2026-05-25',
            'to_date' => '2026-05-25',
            'total_days' => 1,
            'status' => 'approved',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $drivers = $this->getJson('/api/drivers/available?date=2026-05-25&per_page=100')
            ->assertOk()
            ->json('data');
        $vehicles = $this->getJson('/api/vehicles/available?date=2026-05-25&per_page=100')
            ->assertOk()
            ->json('data');

        $driverIds = collect($drivers)->pluck('id')->all();
        $vehicleIds = collect($vehicles)->pluck('id')->all();

        $this->assertNotContains($busyDriver->id, $driverIds);
        $this->assertNotContains($leaveDriver->id, $driverIds);
        $this->assertContains($availableDriver->id, $driverIds);
        $this->assertNotContains($busyVehicle->id, $vehicleIds);
        $this->assertContains($availableVehicle->id, $vehicleIds);
    }

    public function test_trip_lifecycle_actions_start_deliver_complete_and_cancel(): void
    {
        $trip = $this->makeTrip('assigned', '2026-05-26');

        $this->patchJson("/api/trips/{$trip->id}/start")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'in_progress');

        $this->patchJson("/api/trips/{$trip->id}/deliver")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'delivered');

        $this->patchJson("/api/trips/{$trip->id}/complete")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'completed');

        $this->patchJson("/api/trips/{$trip->id}/cancel", ['reason' => 'Customer requested cancellation'])
            ->assertUnprocessable()
            ->assertJsonPath('success', false);

        $cancelTrip = $this->makeTrip('assigned', '2026-05-27');

        $this->patchJson("/api/trips/{$cancelTrip->id}/cancel", ['reason' => 'Customer requested cancellation'])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'cancelled');
    }

    public function test_forbidden_and_not_found_errors_return_expected_statuses(): void
    {
        $otherCompanyVehicle = Vehicle::factory()->create(['status' => 'active']);

        $this->getJson("/api/vehicles/{$otherCompanyVehicle->id}")
            ->assertForbidden();

        $this->getJson('/api/vehicles/999999')
            ->assertNotFound();
    }

    private function makeTrip(string $status, string $scheduledDate): Trip
    {
        return Trip::query()->create([
            'company_id' => $this->company->id,
            'code' => 'TRIP-'.str_replace('-', '', $scheduledDate).'-'.substr(md5($status.microtime()), 0, 6),
            'customer_id' => $this->customer->id,
            'start_point' => 'Warehouse A',
            'end_point' => 'Warehouse B',
            'received_date' => '2026-05-14',
            'scheduled_date' => $scheduledDate,
            'base_price' => 1000000,
            'surcharge_amount' => 0,
            'total_revenue' => 1000000,
            'price' => 1000000,
            'status' => $status,
        ]);
    }
}
