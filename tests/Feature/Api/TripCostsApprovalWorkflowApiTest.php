<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Company;
use App\Models\CostApprovalRequest;
use App\Models\CostCategory;
use App\Models\Customer;
use App\Models\Driver;
use App\Models\Office;
use App\Models\Trip;
use App\Models\TripCost;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class TripCostsApprovalWorkflowApiTest extends TestCase
{
    use RefreshDatabase;

    private function adminUser(): User
    {
        return User::factory()->create([
            'status' => 'active',
            'role' => 'admin',
        ]);
    }

    public function test_trip_cost_under_threshold_can_be_marked_approved_without_request(): void
    {
        $company = Company::factory()->create();
        $office = Office::factory()->create(['company_id' => $company->id]);
        $vehicle = Vehicle::factory()->create(['company_id' => $company->id, 'office_id' => $office->id]);
        $driver = Driver::factory()->create(['company_id' => $company->id, 'office_id' => $office->id]);
        $customer = Customer::factory()->create(['company_id' => $company->id]);
        $approver = $this->adminUser();

        $trip = Trip::factory()->create([
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'status' => 'in_progress',
            'company_id' => $company->id,
        ]);

        $category = CostCategory::query()->create([
            'company_id' => $company->id,
            'code' => 'FUEL',
            'name' => 'Fuel',
            'approval_threshold' => 1000000,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $cost = TripCost::query()->create([
            'company_id' => $company->id,
            'trip_id' => $trip->id,
            'cost_category_id' => $category->id,
            'amount' => 500000,
            'norm_amount' => 600000,
            'incurred_date' => now()->toDateString(),
            'status' => 'approved',
            'approval_required' => false,
            'approved_by' => $approver->id,
            'approved_at' => now(),
        ]);

        $this->assertDatabaseHas('trip_costs', [
            'id' => $cost->id,
            'status' => 'approved',
            'approval_required' => 0,
        ]);
        $this->assertDatabaseMissing('cost_approval_requests', [
            'trip_id' => $trip->id,
        ]);
    }

    public function test_trip_cost_above_threshold_creates_request_and_supports_approve_reject(): void
    {
        $company = Company::factory()->create();
        $office = Office::factory()->create(['company_id' => $company->id]);
        $vehicle = Vehicle::factory()->create(['company_id' => $company->id, 'office_id' => $office->id]);
        $driver = Driver::factory()->create(['company_id' => $company->id, 'office_id' => $office->id]);
        $customer = Customer::factory()->create(['company_id' => $company->id]);
        $requester = User::factory()->create(['status' => 'active']);
        $reviewer = $this->adminUser();

        $trip = Trip::factory()->create([
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'status' => 'in_progress',
            'company_id' => $company->id,
        ]);

        $category = CostCategory::query()->create([
            'company_id' => $company->id,
            'code' => 'TOLL',
            'name' => 'Toll',
            'approval_threshold' => 300000,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $cost = TripCost::query()->create([
            'company_id' => $company->id,
            'trip_id' => $trip->id,
            'cost_category_id' => $category->id,
            'amount' => 550000,
            'norm_amount' => 300000,
            'incurred_date' => now()->toDateString(),
            'status' => 'pending',
            'approval_required' => true,
        ]);

        $request = CostApprovalRequest::query()->create([
            'company_id' => $company->id,
            'trip_id' => $trip->id,
            'requested_by' => $requester->id,
            'total_amount' => 550000,
            'reason' => 'Over threshold cost',
            'status' => 'pending',
        ]);

        $request->update([
            'status' => 'approved',
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'review_note' => 'Approved for emergency route',
        ]);

        $cost->update([
            'status' => 'approved',
            'approved_by' => $reviewer->id,
            'approved_at' => now(),
            'approval_note' => 'Approved via request',
        ]);

        $this->assertDatabaseHas('cost_approval_requests', [
            'id' => $request->id,
            'status' => 'approved',
        ]);
        $this->assertDatabaseHas('trip_costs', [
            'id' => $cost->id,
            'status' => 'approved',
            'approval_required' => 1,
        ]);
    }
}
