<?php

namespace Tests\Feature\Services;

use App\Models\Employee;
use App\Models\Trip;
use App\Services\Lark\LarkBaseSyncService;
use App\Services\Lark\LarkTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class LarkBaseSyncServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_reverse_sync_employees_updates_existing_employee_only(): void
    {
        Queue::fake();

        config()->set('lark.base.enable_reverse_sync', true);
        config()->set('lark.base.app_token', 'app_token_x');
        config()->set('lark.base.employees_table_id', 'tbl_emp');

        $employee = Employee::factory()->create([
            'code' => 'EMP0001',
            'name' => 'Old Name',
            'email' => 'old.employee@example.com',
            'phone' => '0900000000',
            'type' => 'office',
            'status' => 'inactive',
        ]);

        Http::fake([
            'https://open.larksuite.com/open-apis/bitable/v1/apps/*/tables/*/records*' => Http::response([
                'code' => 0,
                'data' => [
                    'items' => [
                        [
                            'record_id' => 'rec_1',
                            'fields' => [
                                'EmployeeID' => (string) $employee->id,
                                'Code' => 'EMP0001',
                                'Name' => 'New Name',
                                'Email' => 'new.employee@example.com',
                                'Phone' => '0911111111',
                                'Type' => 'driver',
                                'Status' => 'active',
                            ],
                        ],
                    ],
                    'has_more' => false,
                    'page_token' => '',
                ],
            ], 200),
        ]);

        $tokenService = $this->createMock(LarkTokenService::class);
        $tokenService->method('getTenantAccessToken')->willReturn('token-abc');

        $service = new LarkBaseSyncService($tokenService);
        $stats = $service->reverseSyncEmployees(50);

        $this->assertSame([
            'fetched' => 1,
            'updated' => 1,
            'skipped' => 0,
            'failed' => 0,
        ], $stats);

        $this->assertDatabaseHas('employees', [
            'id' => $employee->id,
            'name' => 'New Name',
            'email' => 'new.employee@example.com',
            'phone' => '0911111111',
            'type' => 'driver',
            'status' => 'active',
        ]);
    }

    public function test_reverse_sync_trips_skips_when_trip_does_not_exist_locally(): void
    {
        config()->set('lark.base.enable_reverse_sync', true);
        config()->set('lark.base.app_token', 'app_token_x');
        config()->set('lark.base.trips_table_id', 'tbl_trip');

        Http::fake([
            'https://open.larksuite.com/open-apis/bitable/v1/apps/*/tables/*/records*' => Http::response([
                'code' => 0,
                'data' => [
                    'items' => [
                        [
                            'record_id' => 'rec_2',
                            'fields' => [
                                'TripID' => '999999',
                                'Code' => 'TRIP999999',
                                'StartPoint' => 'A',
                                'EndPoint' => 'B',
                                'DistanceKm' => 20,
                                'Price' => 100000,
                                'Status' => 'completed',
                                'DriverID' => 1,
                                'VehicleID' => 1,
                            ],
                        ],
                    ],
                    'has_more' => false,
                    'page_token' => '',
                ],
            ], 200),
        ]);

        $tokenService = $this->createMock(LarkTokenService::class);
        $tokenService->method('getTenantAccessToken')->willReturn('token-abc');

        $service = new LarkBaseSyncService($tokenService);
        $stats = $service->reverseSyncTrips(50);

        $this->assertSame([
            'fetched' => 1,
            'updated' => 0,
            'skipped' => 1,
            'failed' => 0,
        ], $stats);

        $this->assertSame(0, Trip::count());
    }

    public function test_sync_employee_throws_when_lark_returns_business_error(): void
    {
        config()->set('lark.base.app_token', 'app_token_x');
        config()->set('lark.base.employees_table_id', 'tbl_emp');

        Http::fake([
            'https://open.larksuite.com/open-apis/bitable/v1/apps/*/tables/*/records/search' => Http::response([
                'code' => 0,
                'data' => ['items' => []],
            ], 200),
            'https://open.larksuite.com/open-apis/bitable/v1/apps/*/tables/*/records' => Http::response([
                'code' => 99991663,
                'msg' => 'invalid request',
            ], 200),
        ]);
        Queue::fake();

        $employee = Employee::factory()->create();

        $tokenService = $this->createMock(LarkTokenService::class);
        $tokenService->method('getTenantAccessToken')->willReturn('token-abc');

        $service = new LarkBaseSyncService($tokenService);

        try {
            $service->syncEmployee($employee);
            $this->fail('Expected Lark Base sync to throw RuntimeException.');
        } catch (\RuntimeException $e) {
            $this->assertSame('Lark Base sync failed.', $e->getMessage());
        }
    }
}
