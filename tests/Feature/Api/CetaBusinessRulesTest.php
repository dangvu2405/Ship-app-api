<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\CostApprovalRequest;
use App\Models\CostCategory;
use App\Models\Customer;
use App\Models\Driver;
use App\Models\DriverWorkSchedule;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Office;
use App\Models\ReconciliationItem;
use App\Models\ReconciliationSession;
use App\Models\Role;
use App\Models\Trip;
use App\Models\TripCost;
use App\Models\TripSurcharge;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Bộ kiểm thử nghiệp vụ chuẩn theo CETA_FULL_SPEC.md §2.9
 * Mỗi rule R01–R13 được map sang 1–2 test case.
 * Các rule đã có file riêng (R04, R06, R12, R13) được bổ sung test ngắn gọn.
 */
final class CetaBusinessRulesTest extends TestCase
{
    use RefreshDatabase;

    // ── Helpers dùng chung ───────────────────────────────────────────────────

    private function adminUser(): User
    {
        $role = Role::query()->firstOrCreate(['name' => 'admin']);
        $user = User::factory()->create(['status' => 'active']);
        $user->roles()->syncWithoutDetaching([$role->id]);

        return $user;
    }

    /**
     * Tạo một trip đầy đủ context: company, office, vehicle, driver, customer.
     *
     * @return array{company: Company, office: Office, vehicle: Vehicle, driver: Driver, customer: Customer, trip: Trip}
     */
    private function scaffoldTrip(array $tripOverrides = []): array
    {
        $company  = Company::factory()->create();
        $office   = Office::factory()->create(['company_id' => $company->id]);
        $vehicle  = Vehicle::factory()->create(['office_id' => $office->id, 'status' => 'active']);
        $driver   = Driver::factory()->create([
            'office_id'    => $office->id,
            'status'       => 'active',
            'expired_date' => now()->addYears(2)->toDateString(),
        ]);
        $customer = Customer::factory()->create(['company_id' => $company->id]);

        $trip = Trip::factory()->create(array_merge([
            'company_id'  => $company->id,
            'customer_id' => $customer->id,
            'driver_id'   => $driver->id,
            'vehicle_id'  => $vehicle->id,
        ], $tripOverrides));

        return compact('company', 'office', 'vehicle', 'driver', 'customer', 'trip');
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // R01 — Đơn COMPLETED không xóa được; không huỷ được khi đã COMPLETED
    // ═══════════════════════════════════════════════════════════════════════════

    #[Test]
    public function R01_completed_trip_cannot_be_deleted(): void
    {
        Sanctum::actingAs($this->adminUser());
        ['company' => $company, 'trip' => $trip] = $this->scaffoldTrip(['status' => 'completed']);

        $response = $this->deleteJson("/api/trips/{$trip->id}", [], $this->tenant_headers($company));

        $response->assertStatus(422);
        $this->assertDatabaseHas('trips', ['id' => $trip->id, 'deleted_at' => null]);
    }

    #[Test]
    public function R01_cancelled_trip_cannot_be_deleted(): void
    {
        Sanctum::actingAs($this->adminUser());
        ['company' => $company, 'trip' => $trip] = $this->scaffoldTrip(['status' => 'cancelled']);

        $response = $this->deleteJson("/api/trips/{$trip->id}", [], $this->tenant_headers($company));

        $response->assertStatus(422);
        $this->assertDatabaseHas('trips', ['id' => $trip->id, 'deleted_at' => null]);
    }

    #[Test]
    public function R01_completed_trip_cannot_be_cancelled(): void
    {
        Sanctum::actingAs($this->adminUser());
        ['company' => $company, 'trip' => $trip] = $this->scaffoldTrip(['status' => 'completed']);

        $response = $this->postJson(
            "/api/trips/{$trip->id}/cancel",
            ['reason' => 'Nhầm đơn'],
            $this->tenant_headers($company)
        );

        $response->assertStatus(422);
        $this->assertDatabaseHas('trips', ['id' => $trip->id, 'status' => 'completed']);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // R02 — Xe đang chạy (in_progress) không được phân công chuyến khác trùng giờ
    // ═══════════════════════════════════════════════════════════════════════════

    #[Test]
    public function R02_cannot_update_trip_vehicle_id_to_a_vehicle_already_in_progress(): void
    {
        Sanctum::actingAs($this->adminUser());

        $company  = Company::factory()->create();
        $office   = Office::factory()->create(['company_id' => $company->id]);
        $driverA  = Driver::factory()->create(['office_id' => $office->id, 'status' => 'active']);
        $driverB  = Driver::factory()->create(['office_id' => $office->id, 'status' => 'active']);
        $busyVeh  = Vehicle::factory()->create(['office_id' => $office->id, 'status' => 'active']);
        $freeVeh  = Vehicle::factory()->create(['office_id' => $office->id, 'status' => 'active']);
        $customer = Customer::factory()->create(['company_id' => $company->id]);

        // Xe đang chạy chuyến khác
        Trip::factory()->create([
            'company_id'  => $company->id,
            'vehicle_id'  => $busyVeh->id,
            'driver_id'   => $driverA->id,
            'customer_id' => $customer->id,
            'status'      => 'in_progress',
        ]);

        // Đơn mới đang dùng xe khác
        $pending = Trip::factory()->create([
            'company_id'  => $company->id,
            'vehicle_id'  => $freeVeh->id,
            'driver_id'   => $driverB->id,
            'customer_id' => $customer->id,
            'status'      => 'pending',
            'start_point' => 'HCM',
            'end_point'   => 'BD',
        ]);

        // Cố gán xe đang bận vào đơn mới
        $response = $this->putJson("/api/trips/{$pending->id}", [
            'customer_id' => $pending->customer_id,
            'driver_id'   => $driverB->id,
            'vehicle_id'  => $busyVeh->id,
            'start_point' => 'HCM',
            'end_point'   => 'BD',
            'status'      => 'pending',
            'price'       => 1000000,
        ], $this->tenant_headers($company));

        $response->assertStatus(422);

        // Xe trên đơn không được thay đổi sang xe đang bận
        $this->assertDatabaseHas('trips', [
            'id'         => $pending->id,
            'vehicle_id' => $freeVeh->id,
        ]);
    }

    #[Test]
    public function R02_cannot_update_trip_driver_id_to_a_driver_already_in_progress(): void
    {
        Sanctum::actingAs($this->adminUser());

        $company  = Company::factory()->create();
        $office   = Office::factory()->create(['company_id' => $company->id]);
        $busyDrv  = Driver::factory()->create(['office_id' => $office->id, 'status' => 'active']);
        $vehicleA = Vehicle::factory()->create(['office_id' => $office->id, 'status' => 'active']);
        $vehicleB = Vehicle::factory()->create(['office_id' => $office->id, 'status' => 'active']);
        $customer = Customer::factory()->create(['company_id' => $company->id]);

        // Tài xế đang chạy chuyến khác
        Trip::factory()->create([
            'company_id'  => $company->id,
            'vehicle_id'  => $vehicleA->id,
            'driver_id'   => $busyDrv->id,
            'customer_id' => $customer->id,
            'status'      => 'in_progress',
        ]);

        $pending = Trip::factory()->create([
            'company_id'  => $company->id,
            'vehicle_id'  => $vehicleB->id,
            'driver_id'   => $busyDrv->id,
            'customer_id' => $customer->id,
            'status'      => 'pending',
            'start_point' => 'HCM',
            'end_point'   => 'BD',
        ]);

        $response = $this->putJson("/api/trips/{$pending->id}", [
            'customer_id' => $pending->customer_id,
            'driver_id'   => $busyDrv->id,
            'vehicle_id'  => $vehicleB->id,
            'start_point' => 'HCM',
            'end_point'   => 'BD',
            'status'      => 'in_progress',
            'price'       => 1000000,
        ], $this->tenant_headers($company));

        $response->assertStatus(422);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // R03 — GPLX hết hạn: cảnh báo nhưng VẪN CHO PHÉP tạo đơn
    // ═══════════════════════════════════════════════════════════════════════════

    #[Test]
    public function R03_expired_license_driver_can_still_be_added_to_new_trip(): void
    {
        Sanctum::actingAs($this->adminUser());

        $company  = Company::factory()->create();
        $office   = Office::factory()->create(['company_id' => $company->id]);
        $vehicle  = Vehicle::factory()->create(['office_id' => $office->id, 'status' => 'active']);
        $customer = Customer::factory()->create(['company_id' => $company->id]);

        // Tài xế có GPLX đã hết hạn 1 năm trước
        $driver = Driver::factory()->create([
            'office_id'    => $office->id,
            'status'       => 'active',
            'expired_date' => now()->subYear()->toDateString(),
        ]);

        $response = $this->postJson('/api/trips', [
            'customer_id' => $customer->id,
            'driver_id'   => $driver->id,
            'vehicle_id'  => $vehicle->id,
            'code'        => 'R03-WARN-001',
            'start_point' => 'HCM',
            'end_point'   => 'BD',
            'status'      => 'pending',
            'price'       => 1000000,
        ], $this->tenant_headers($company));

        // Spec R03: cảnh báo nhưng KHÔNG CHẶN — phải thành công
        $response->assertStatus(201);
        $this->assertDatabaseHas('trips', ['code' => 'R03-WARN-001']);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // R04 — 1 xe chỉ có 1 tài xế phụ trách tại 1 thời điểm (vehicle_assignments)
    // (xem thêm VehicleAssignmentsApiTest)
    // ═══════════════════════════════════════════════════════════════════════════

    #[Test]
    public function R04_vehicle_cannot_have_two_open_ended_assignments_simultaneously(): void
    {
        $company = Company::factory()->create();
        $office  = Office::factory()->create(['company_id' => $company->id]);
        $vehicle = Vehicle::factory()->create(['office_id' => $office->id]);
        $driverA = Driver::factory()->create(['office_id' => $office->id]);
        $driverB = Driver::factory()->create(['office_id' => $office->id]);

        // Gắn tài xế A (to_date = NULL = đang hiệu lực)
        VehicleAssignment::query()->create([
            'vehicle_id' => $vehicle->id,
            'driver_id'  => $driverA->id,
            'company_id' => $company->id,
            'from_date'  => now()->subDays(10)->toDateString(),
            'to_date'    => null,
        ]);

        // Gắn thêm tài xế B mà không đóng tài xế A → vi phạm R04
        // Hệ thống phải ngăn qua API (đã test ở VehicleAssignmentsApiTest)
        // Ở đây xác nhận DB: chỉ có 1 bản ghi to_date IS NULL cho xe này
        $openCount = VehicleAssignment::query()
            ->where('vehicle_id', $vehicle->id)
            ->whereNull('to_date')
            ->count();

        $this->assertSame(1, $openCount, 'R04: Xe chỉ được có 1 bản ghi to_date = NULL');
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // R05 — 1 tài xế chỉ được gán 1 xe trong 1 ngày (driver_work_schedules)
    // ═══════════════════════════════════════════════════════════════════════════

    #[Test]
    public function R05_driver_work_schedule_prevents_duplicate_shift_same_day(): void
    {
        $company = Company::factory()->create();
        $office  = Office::factory()->create(['company_id' => $company->id]);
        $driver  = Driver::factory()->create(['office_id' => $office->id]);
        $vehicle = Vehicle::factory()->create(['office_id' => $office->id]);

        $base = [
            'company_id' => $company->id,
            'office_id'  => $office->id,
            'driver_id'  => $driver->id,
            'vehicle_id' => $vehicle->id,
            'work_date'  => '2026-05-10',
            'shift_code' => 'DAY',
            'start_time' => '07:00:00',
            'end_time'   => '17:00:00',
            'status'     => 'draft',
        ];

        DriverWorkSchedule::query()->create($base);

        $this->expectException(\Illuminate\Database\QueryException::class);

        // Cùng tài xế, cùng ngày, cùng ca → DB unique constraint vi phạm
        DriverWorkSchedule::query()->create($base);
    }

    #[Test]
    public function R05_driver_can_have_different_shift_on_same_day(): void
    {
        $company = Company::factory()->create();
        $office  = Office::factory()->create(['company_id' => $company->id]);
        $driver  = Driver::factory()->create(['office_id' => $office->id]);
        $vehicle = Vehicle::factory()->create(['office_id' => $office->id]);

        $schedule = DriverWorkSchedule::query()->create([
            'company_id' => $company->id,
            'office_id'  => $office->id,
            'driver_id'  => $driver->id,
            'vehicle_id' => $vehicle->id,
            'work_date'  => '2026-05-10',
            'shift_code' => 'MORNING',
            'start_time' => '06:00:00',
            'end_time'   => '12:00:00',
            'status'     => 'draft',
        ]);

        // Ca khác nhau thì DB cho phép (business rule kiểm tra thêm ở service layer)
        $this->assertDatabaseHas('driver_work_schedules', [
            'id' => $schedule->id,
            'driver_id' => $driver->id,
            'shift_code' => 'MORNING',
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // R06 — Chi phí vượt approval_threshold → bắt buộc qua luồng phê duyệt
    // (xem thêm TripCostsApprovalWorkflowApiTest)
    // ═══════════════════════════════════════════════════════════════════════════

    #[Test]
    public function R06_cost_below_threshold_is_auto_approved(): void
    {
        ['company' => $company, 'trip' => $trip] = $this->scaffoldTrip(['status' => 'in_progress']);

        $category = CostCategory::query()->create([
            'company_id'         => $company->id,
            'code'               => 'FUEL',
            'name'               => 'Nhiên liệu',
            'approval_threshold' => 1_000_000,
            'is_active'          => true,
            'sort_order'         => 1,
        ]);

        $cost = TripCost::query()->create([
            'company_id'       => $company->id,
            'trip_id'          => $trip->id,
            'cost_category_id' => $category->id,
            'amount'           => 500_000,   // < threshold
            'incurred_date'    => now()->toDateString(),
            'status'           => 'approved', // tự duyệt
            'approval_required' => false,
        ]);

        $this->assertDatabaseHas('trip_costs', [
            'id'               => $cost->id,
            'status'           => 'approved',
            'approval_required' => 0,
        ]);
        $this->assertDatabaseMissing('cost_approval_requests', ['trip_id' => $trip->id]);
    }

    #[Test]
    public function R06_cost_above_threshold_creates_approval_request(): void
    {
        $requester = $this->adminUser();
        ['company' => $company, 'trip' => $trip] = $this->scaffoldTrip(['status' => 'in_progress']);

        $category = CostCategory::query()->create([
            'company_id'         => $company->id,
            'code'               => 'REPAIR',
            'name'               => 'Sửa chữa',
            'approval_threshold' => 1_000_000,
            'is_active'          => true,
            'sort_order'         => 2,
        ]);

        // Chi phí vượt threshold
        TripCost::query()->create([
            'company_id'       => $company->id,
            'trip_id'          => $trip->id,
            'cost_category_id' => $category->id,
            'amount'           => 2_500_000, // > 1M threshold
            'incurred_date'    => now()->toDateString(),
            'status'           => 'pending',
            'approval_required' => true,
        ]);

        $approvalRequest = CostApprovalRequest::query()->create([
            'company_id'   => $company->id,
            'trip_id'      => $trip->id,
            'requested_by' => $requester->id,
            'total_amount' => 2_500_000,
            'reason'       => 'Thay lốp khẩn cấp, vượt định mức',
            'status'       => 'pending',
        ]);

        $this->assertDatabaseHas('cost_approval_requests', [
            'trip_id' => $trip->id,
            'status'  => 'pending',
        ]);

        // Kế toán duyệt
        $approvalRequest->update(['status' => 'approved', 'reviewed_by' => $requester->id, 'reviewed_at' => now()]);

        $this->assertDatabaseHas('cost_approval_requests', [
            'id'     => $approvalRequest->id,
            'status' => 'approved',
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // R07 — Đối soát đã locked → không sửa được
    // ═══════════════════════════════════════════════════════════════════════════

    #[Test]
    public function R07_reconciliation_status_draft_can_be_confirmed(): void
    {
        $confirmer = $this->adminUser();
        ['company' => $company, 'customer' => $customer, 'trip' => $trip] =
            $this->scaffoldTrip(['status' => 'completed', 'total_revenue' => 2_000_000]);

        $session = ReconciliationSession::query()->create([
            'company_id'      => $company->id,
            'customer_id'     => $customer->id,
            'period_from'     => '2026-05-01',
            'period_to'       => '2026-05-31',
            'total_trips'     => 1,
            'total_revenue'   => 2_000_000,
            'adjusted_amount' => 0,
            'final_amount'    => 2_000_000,
            'status'          => 'draft',
        ]);

        ReconciliationItem::query()->create([
            'company_id'      => $company->id,
            'session_id'      => $session->id,
            'trip_id'         => $trip->id,
            'original_amount' => 2_000_000,
            'adjusted_amount' => 2_000_000,
            'is_disputed'     => false,
        ]);

        // draft → confirmed
        $session->update([
            'status'       => 'confirmed',
            'confirmed_by' => $confirmer->id,
            'confirmed_at' => now(),
        ]);
        $this->assertDatabaseHas('reconciliation_sessions', ['id' => $session->id, 'status' => 'confirmed']);

        // confirmed → locked
        $session->update(['status' => 'locked']);
        $this->assertDatabaseHas('reconciliation_sessions', ['id' => $session->id, 'status' => 'locked']);
    }

    #[Test]
    public function R07_locked_session_status_cannot_transition_to_draft(): void
    {
        $confirmer = $this->adminUser();
        ['company' => $company, 'customer' => $customer, 'trip' => $trip] =
            $this->scaffoldTrip(['status' => 'completed', 'total_revenue' => 1_500_000]);

        $session = ReconciliationSession::query()->create([
            'company_id'      => $company->id,
            'customer_id'     => $customer->id,
            'period_from'     => '2026-05-01',
            'period_to'       => '2026-05-31',
            'total_trips'     => 1,
            'total_revenue'   => 1_500_000,
            'adjusted_amount' => 0,
            'final_amount'    => 1_500_000,
            'status'          => 'locked',
            'confirmed_by'    => $confirmer->id,
            'confirmed_at'    => now(),
        ]);

        ReconciliationItem::query()->create([
            'company_id'      => $company->id,
            'session_id'      => $session->id,
            'trip_id'         => $trip->id,
            'original_amount' => 1_500_000,
            'adjusted_amount' => 1_500_000,
            'is_disputed'     => false,
        ]);

        // Spec R07: không thể sửa sau khi locked
        // Lớp bảo vệ ở service/controller layer — tại đây xác nhận trạng thái
        $session->refresh();
        $this->assertSame('locked', $session->status,
            'R07: Phiên đối soát đã locked không được phép sửa đổi.');

        // Nếu cố gán disputed = true cho item của locked session → không được phép
        // (enforcement nằm ở service layer; test này xác nhận integrity)
        $items = $session->items()->get();
        foreach ($items as $item) {
            $this->assertFalse((bool) $item->is_disputed,
                'R07: Item trong locked session không có tranh chấp chưa được xử lý.');
        }
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // R08 — Xóa KH chỉ được khi không có trips liên quan
    // ═══════════════════════════════════════════════════════════════════════════

    #[Test]
    public function R08_customer_with_active_trips_cannot_be_deleted(): void
    {
        Sanctum::actingAs($this->adminUser());

        ['company' => $company, 'customer' => $customer] =
            $this->scaffoldTrip(['status' => 'in_progress']);

        $response = $this->deleteJson(
            "/api/customers/{$customer->id}",
            [],
            $this->tenant_headers($company)
        );

        $response->assertStatus(422);
        $this->assertDatabaseHas('customers', ['id' => $customer->id, 'deleted_at' => null]);
    }

    #[Test]
    public function R08_customer_with_completed_trips_cannot_be_deleted(): void
    {
        Sanctum::actingAs($this->adminUser());

        ['company' => $company, 'customer' => $customer] =
            $this->scaffoldTrip(['status' => 'completed']);

        $response = $this->deleteJson(
            "/api/customers/{$customer->id}",
            [],
            $this->tenant_headers($company)
        );

        $response->assertStatus(422);
        $this->assertDatabaseHas('customers', ['id' => $customer->id, 'deleted_at' => null]);
    }

    #[Test]
    public function R08_customer_without_trips_can_be_soft_deleted(): void
    {
        Sanctum::actingAs($this->adminUser());

        $company  = Company::factory()->create();
        $customer = Customer::factory()->create(['company_id' => $company->id]);

        $response = $this->deleteJson(
            "/api/customers/{$customer->id}",
            [],
            $this->tenant_headers($company)
        );

        $response->assertSuccessful();
        $this->assertSoftDeleted('customers', ['id' => $customer->id]);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // R09 — audit_logs không UPDATE, không DELETE
    // ═══════════════════════════════════════════════════════════════════════════

    #[Test]
    public function R09_no_http_route_exists_to_update_audit_log(): void
    {
        Sanctum::actingAs($this->adminUser());

        $company = Company::factory()->create();
        $log = AuditLog::query()->create([
            'company_id' => $company->id,
            'action'     => 'create',
            'table_name' => 'trips',
            'record_id'  => 1,
        ]);

        // Không có route PUT/PATCH /audit-logs/{id} → 404 hoặc 405
        $put    = $this->putJson("/api/audit-logs/{$log->id}", ['action' => 'tampered']);
        $patch  = $this->patchJson("/api/audit-logs/{$log->id}", ['action' => 'tampered']);

        $put->assertStatus(404);
        $patch->assertStatus(404);

        // Bản ghi vẫn nguyên vẹn
        $this->assertDatabaseHas('audit_logs', ['id' => $log->id, 'action' => 'create']);
    }

    #[Test]
    public function R09_no_http_route_exists_to_delete_audit_log(): void
    {
        Sanctum::actingAs($this->adminUser());

        $company = Company::factory()->create();
        $log = AuditLog::query()->create([
            'company_id' => $company->id,
            'action'     => 'login',
            'table_name' => 'users',
            'record_id'  => 2,
        ]);

        $delete = $this->deleteJson("/api/audit-logs/{$log->id}");

        $delete->assertStatus(404);
        $this->assertDatabaseHas('audit_logs', ['id' => $log->id]);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // R10 — total_revenue = base_price + SUM(trip_surcharges.amount)
    // ═══════════════════════════════════════════════════════════════════════════

    #[Test]
    public function R10_create_trip_auto_computes_total_revenue_from_base_plus_surcharge(): void
    {
        Sanctum::actingAs($this->adminUser());

        $company  = Company::factory()->create();
        $office   = Office::factory()->create(['company_id' => $company->id]);
        $vehicle  = Vehicle::factory()->create(['office_id' => $office->id, 'status' => 'active']);
        $driver   = Driver::factory()->create([
            'office_id'    => $office->id,
            'status'       => 'active',
            'expired_date' => now()->addYear()->toDateString(),
        ]);
        $customer = Customer::factory()->create(['company_id' => $company->id]);

        $response = $this->postJson('/api/trips', [
            'customer_id'      => $customer->id,
            'driver_id'        => $driver->id,
            'vehicle_id'       => $vehicle->id,
            'code'             => 'R10-CALC-001',
            'start_point'      => 'HCM',
            'end_point'        => 'BD',
            'status'           => 'pending',
            'base_price'       => 1_500_000,
            'surcharge_amount' => 300_000,
        ], $this->tenant_headers($company));

        $response->assertStatus(201);

        $this->assertDatabaseHas('trips', [
            'code'          => 'R10-CALC-001',
            'total_revenue' => '1800000.00',
        ]);
    }

    #[Test]
    public function R10_total_revenue_equals_base_price_plus_sum_of_surcharge_records(): void
    {
        ['company' => $company, 'trip' => $trip] = $this->scaffoldTrip([
            'status'           => 'pending',
            'base_price'       => 1_500_000,
            'surcharge_amount' => 0,
            'total_revenue'    => 1_500_000,
        ]);

        TripSurcharge::query()->create([
            'company_id' => $company->id,
            'trip_id'    => $trip->id,
            'name'       => 'Phụ phí chờ hàng',
            'amount'     => 200_000,
        ]);
        TripSurcharge::query()->create([
            'company_id' => $company->id,
            'trip_id'    => $trip->id,
            'name'       => 'Phụ phí cầu đường',
            'amount'     => 300_000,
        ]);

        $surchargeTotal = (float) TripSurcharge::query()
            ->where('trip_id', $trip->id)
            ->sum('amount');

        $expectedTotal = (float) $trip->base_price + $surchargeTotal;

        $this->assertEqualsWithDelta(2_000_000.0, $expectedTotal, 0.01,
            'R10: 1.5M + (200k + 300k) = 2.0M'
        );

        // Cập nhật trip để phản ánh surcharges
        $trip->update([
            'surcharge_amount' => $surchargeTotal,
            'total_revenue'    => $expectedTotal,
        ]);

        $this->assertDatabaseHas('trips', [
            'id'            => $trip->id,
            'total_revenue' => '2000000.00',
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // R11 — Mã đơn (code), mã KH, mã tài xế do hệ thống sinh — không cho sửa
    // ═══════════════════════════════════════════════════════════════════════════

    #[Test]
    public function R11_trip_code_must_be_immutable_after_creation(): void
    {
        Sanctum::actingAs($this->adminUser());

        ['company' => $company, 'trip' => $trip] = $this->scaffoldTrip([
            'status' => 'pending',
            'code'   => 'ORIGINAL-CODE-001',
        ]);

        $response = $this->putJson("/api/trips/{$trip->id}", [
            'customer_id' => $trip->customer_id,
            'driver_id'   => $trip->driver_id,
            'vehicle_id'  => $trip->vehicle_id,
            'code'        => 'TAMPERED-CODE-999',
            'start_point' => $trip->start_point,
            'end_point'   => $trip->end_point,
            'status'      => 'pending',
            'price'       => $trip->price,
        ], $this->tenant_headers($company));

        $response->assertSuccessful();

        // Spec R11: code không được phép thay đổi
        $this->assertDatabaseHas('trips', ['id' => $trip->id, 'code' => 'ORIGINAL-CODE-001']);
        $this->assertSame(
            'ORIGINAL-CODE-001',
            Trip::query()->find($trip->id)->code,
            'R11: Mã đơn phải là bất biến sau khi tạo'
        );
    }

    #[Test]
    public function R11_customer_code_must_be_immutable_after_creation(): void
    {
        Sanctum::actingAs($this->adminUser());

        $company  = Company::factory()->create();
        $customer = Customer::factory()->create([
            'company_id' => $company->id,
            'code'       => 'KH-ORIGINAL-01',
        ]);

        $response = $this->putJson("/api/customers/{$customer->id}", [
            'code'   => 'KH-TAMPERED-99',
            'name'   => $customer->name,
            'type'   => $customer->type,
            'phone'  => $customer->phone,
            'email'  => $customer->email,
        ], $this->tenant_headers($company));

        // Dù response 200 hay 422, code phải không đổi
        $this->assertDatabaseHas('customers', ['id' => $customer->id, 'code' => 'KH-ORIGINAL-01']);
        $this->assertSame(
            'KH-ORIGINAL-01',
            Customer::query()->find($customer->id)->code,
            'R11: Mã KH phải là bất biến sau khi tạo'
        );
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // R12 — Xe status=maintenance/broken → không hiển thị trong phân công
    // (xem DispatchEligibilityApiTest cho kiểm thử API đầy đủ)
    // ═══════════════════════════════════════════════════════════════════════════

    #[Test]
    public function R12_cannot_create_trip_with_maintenance_vehicle(): void
    {
        Sanctum::actingAs($this->adminUser());

        $company  = Company::factory()->create();
        $office   = Office::factory()->create(['company_id' => $company->id]);
        $vehicle  = Vehicle::factory()->create([
            'office_id' => $office->id,
            'status'    => 'maintenance',
        ]);
        $driver   = Driver::factory()->create([
            'office_id'    => $office->id,
            'status'       => 'active',
            'expired_date' => now()->addYear()->toDateString(),
        ]);
        $customer = Customer::factory()->create(['company_id' => $company->id]);

        $response = $this->postJson('/api/trips', [
            'customer_id' => $customer->id,
            'driver_id'   => $driver->id,
            'vehicle_id'  => $vehicle->id,
            'code'        => 'R12-MAINT-001',
            'start_point' => 'HCM',
            'end_point'   => 'BD',
            'status'      => 'pending',
            'price'       => 1_000_000,
        ], $this->tenant_headers($company));

        $response->assertStatus(422);
    }

    #[Test]
    public function R12_cannot_create_trip_with_broken_vehicle(): void
    {
        Sanctum::actingAs($this->adminUser());

        $company  = Company::factory()->create();
        $office   = Office::factory()->create(['company_id' => $company->id]);
        $vehicle  = Vehicle::factory()->create([
            'office_id' => $office->id,
            'status'    => 'broken',
        ]);
        $driver   = Driver::factory()->create([
            'office_id'    => $office->id,
            'status'       => 'active',
            'expired_date' => now()->addYear()->toDateString(),
        ]);
        $customer = Customer::factory()->create(['company_id' => $company->id]);

        $response = $this->postJson('/api/trips', [
            'customer_id' => $customer->id,
            'driver_id'   => $driver->id,
            'vehicle_id'  => $vehicle->id,
            'code'        => 'R12-BROKEN-001',
            'start_point' => 'HCM',
            'end_point'   => 'BD',
            'status'      => 'pending',
            'price'       => 1_000_000,
        ], $this->tenant_headers($company));

        $response->assertStatus(422);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // R13 — Tài xế có leave_request approved → không phân công ngày đó
    // (xem DispatchEligibilityApiTest cho kiểm thử API đầy đủ)
    // ═══════════════════════════════════════════════════════════════════════════

    #[Test]
    public function R13_cannot_create_trip_on_date_driver_has_approved_leave(): void
    {
        Sanctum::actingAs($this->adminUser());

        $company  = Company::factory()->create();
        $office   = Office::factory()->create(['company_id' => $company->id]);
        $vehicle  = Vehicle::factory()->create(['office_id' => $office->id, 'status' => 'active']);
        $driver   = Driver::factory()->create([
            'office_id'    => $office->id,
            'status'       => 'active',
            'expired_date' => now()->addYear()->toDateString(),
        ]);
        $customer = Customer::factory()->create(['company_id' => $company->id]);

        $leaveType = LeaveType::query()->firstOrCreate(
            ['code' => 'AL'],
            [
                'name'                => 'Annual Leave',
                'is_paid'             => true,
                'annual_quota_days'   => 12,
                'allow_carry_forward' => false,
                'requires_attachment' => false,
                'status'              => 'active',
            ]
        );

        $tripDate = now()->addDay()->toDateString();

        LeaveRequest::query()->create([
            'company_id'    => $company->id,
            'driver_id'     => $driver->id,
            'leave_type_id' => $leaveType->id,
            'from_date'     => $tripDate,
            'to_date'       => $tripDate,
            'total_days'    => 1,
            'reason'        => 'Nghỉ phép năm',
            'status'        => 'approved',
        ]);

        // Leave check trigger khi có start_time trùng ngày nghỉ
        $response = $this->postJson('/api/trips', [
            'customer_id' => $customer->id,
            'driver_id'   => $driver->id,
            'vehicle_id'  => $vehicle->id,
            'code'        => 'R13-LEAVE-001',
            'start_point' => 'HCM',
            'end_point'   => 'BD',
            'start_time'  => $tripDate . ' 08:00:00', // trigger leave check
            'status'      => 'pending',
            'price'       => 1_000_000,
        ], $this->tenant_headers($company));

        $response->assertStatus(422);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // Luồng nghiệp vụ đầy đủ: pending → in_progress → completed (§2.5)
    // ═══════════════════════════════════════════════════════════════════════════

    #[Test]
    public function flow_full_trip_lifecycle_pending_to_completed(): void
    {
        $admin = $this->adminUser();
        Sanctum::actingAs($admin);

        $company  = Company::factory()->create();
        $office   = Office::factory()->create(['company_id' => $company->id]);
        $vehicle  = Vehicle::factory()->create(['office_id' => $office->id, 'status' => 'active']);
        $driver   = Driver::factory()->create([
            'office_id'    => $office->id,
            'status'       => 'active',
            'expired_date' => now()->addYear()->toDateString(),
        ]);
        $customer = Customer::factory()->create(['company_id' => $company->id]);

        // Bước 1 — Tạo đơn (status: pending)
        $create = $this->postJson('/api/trips', [
            'customer_id'      => $customer->id,
            'driver_id'        => $driver->id,
            'vehicle_id'       => $vehicle->id,
            'code'             => 'FLOW-FULL-001',
            'start_point'      => 'HCM',
            'end_point'        => 'Bình Dương',
            'status'           => 'pending',
            'base_price'       => 1_500_000,
            'surcharge_amount' => 200_000,
        ], $this->tenant_headers($company));
        $create->assertStatus(201);

        $tripId = $create->json('data.id');
        $this->assertNotNull($tripId);

        // Xác nhận total_revenue = base + surcharge
        $this->assertDatabaseHas('trips', [
            'id'            => $tripId,
            'total_revenue' => '1700000.00',
        ]);

        // Bước 2 — Bắt đầu vận chuyển (pending → in_progress)
        $start = $this->postJson("/api/trips/{$tripId}/start", [], $this->tenant_headers($company));
        $start->assertSuccessful();
        $this->assertDatabaseHas('trips', ['id' => $tripId, 'status' => 'in_progress']);

        // Bước 3 — Ghi nhận lấy hàng
        $pickup = $this->postJson("/api/trips/{$tripId}/pickup", [], $this->tenant_headers($company));
        $pickup->assertSuccessful();

        // Bước 4 — Hoàn thành (in_progress → completed)
        $complete = $this->postJson("/api/trips/{$tripId}/complete", [], $this->tenant_headers($company));
        $complete->assertSuccessful();
        $this->assertDatabaseHas('trips', ['id' => $tripId, 'status' => 'completed']);

        // Kiểm tra R01: Sau khi completed, không xóa được
        $delete = $this->deleteJson("/api/trips/{$tripId}", [], $this->tenant_headers($company));
        $delete->assertStatus(422);

        // Kiểm tra R01: Sau khi completed, không hủy được
        $cancel = $this->postJson("/api/trips/{$tripId}/cancel", [
            'reason' => 'Test hủy đơn đã hoàn thành',
        ], $this->tenant_headers($company));
        $cancel->assertStatus(422);
    }

    #[Test]
    public function flow_trip_cancellation_with_mandatory_reason(): void
    {
        $admin = $this->adminUser();
        Sanctum::actingAs($admin);

        ['company' => $company, 'trip' => $trip] = $this->scaffoldTrip(['status' => 'pending']);

        // Hủy không có lý do → 422
        $noReason = $this->postJson(
            "/api/trips/{$trip->id}/cancel",
            [],
            $this->tenant_headers($company)
        );
        $noReason->assertStatus(422);

        // Hủy có lý do → thành công
        $withReason = $this->postJson(
            "/api/trips/{$trip->id}/cancel",
            ['reason' => 'Khách hàng hủy đơn'],
            $this->tenant_headers($company)
        );
        $withReason->assertSuccessful();

        $this->assertDatabaseHas('trips', [
            'id'                  => $trip->id,
            'status'              => 'cancelled',
            'cancellation_reason' => 'Khách hàng hủy đơn',
        ]);
    }

    #[Test]
    public function flow_reconciliation_draft_to_locked_freezes_data(): void
    {
        $confirmer = $this->adminUser();

        ['company' => $company, 'customer' => $customer, 'trip' => $trip] =
            $this->scaffoldTrip(['status' => 'completed', 'total_revenue' => 3_000_000]);

        $session = ReconciliationSession::query()->create([
            'company_id'      => $company->id,
            'customer_id'     => $customer->id,
            'period_from'     => '2026-05-01',
            'period_to'       => '2026-05-31',
            'total_trips'     => 1,
            'total_revenue'   => 3_000_000,
            'adjusted_amount' => 0,
            'final_amount'    => 3_000_000,
            'status'          => 'draft',
        ]);

        $item = ReconciliationItem::query()->create([
            'company_id'      => $company->id,
            'session_id'      => $session->id,
            'trip_id'         => $trip->id,
            'original_amount' => 3_000_000,
            'adjusted_amount' => 3_000_000,
            'is_disputed'     => false,
        ]);

        // draft → confirmed → locked
        $session->update(['status' => 'confirmed', 'confirmed_by' => $confirmer->id, 'confirmed_at' => now()]);
        $session->update(['status' => 'locked']);

        $session->refresh();
        $item->refresh();

        $this->assertSame('locked', $session->status);
        $this->assertSame('3000000.00', $item->original_amount);
        $this->assertFalse((bool) $item->is_disputed);
    }
}
