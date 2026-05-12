<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\CetaResourceRequest;
use App\Services\Finance\FinanceService;
use App\Services\Fleet\FleetService;
use App\Services\Report\ReportService;
use App\Services\Trip\TripService;
use App\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

final class CetaSpecController extends BaseController
{
    /** @var array<string, string> */
    private const TABLES = [
        'cargo-types' => 'cargo_types',
        'companies' => 'companies',
        'departments' => 'departments',
        'employees' => 'employees',
        'offices' => 'offices',
        'positions' => 'positions',
        'cost-approvals' => 'cost_approval_requests',
        'cost-categories' => 'cost_categories',
        'customer-groups' => 'customer_groups',
        'customers' => 'customers',
        'driver-documents' => 'driver_documents',
        'driver-teams' => 'driver_teams',
        'drivers' => 'drivers',
        'leave-requests' => 'leave_requests',
        'leave-types' => 'leave_types',
        'locations' => 'locations',
        'maintenance-records' => 'maintenance_records',
        'maintenance-schedules' => 'maintenance_schedules',
        'notifications' => 'notifications',
        'order-status-configs' => 'order_status_configs',
        'overtime' => 'overtime_requests',
        'overtimes' => 'overtime_requests',
        'payrolls' => 'payrolls',
        'payroll-driver-lines' => 'payroll_lines',
        'payments' => 'payment_records',
        'price-list-items' => 'price_list_items',
        'price-lists' => 'price_lists',
        'invoice-status-histories' => 'invoice_status_histories',
        'invoices' => 'invoices',
        'report-caches' => 'report_caches',
        'reconciliation-items' => 'reconciliation_items',
        'reconciliations' => 'reconciliation_sessions',
        'route-templates' => 'route_templates',
        'spare-parts' => 'spare_parts',
        'transport-requests' => 'transport_requests',
        'trip-costs' => 'trip_costs',
        'trip-documents' => 'trip_documents',
        'trip-stops' => 'trip_stops',
        'trip-surcharges' => 'trip_surcharges',
        'trips' => 'trips',
        'upload' => 'trip_documents',
        'user-permissions' => 'user_permissions',
        'users' => 'users',
        'vehicle-assignments' => 'vehicle_assignments',
        'vehicle-documents' => 'vehicle_documents',
        'vehicle-types' => 'vehicle_types',
        'vehicles' => 'vehicles',
        'work-schedules' => 'driver_work_schedules',
    ];

    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly TripService $tripService,
        private readonly FinanceService $financeService,
        private readonly FleetService $fleetService,
        private readonly ReportService $reportService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $table = $this->table((string) $request->route('resource'));
        $query = $this->scopedQuery($table);

        $this->applySearch($query, $request, $table);
        $this->applyFilters($query, $request, $table);

        $perPage = max(1, min(100, (int) $request->query('per_page', 15)));
        $this->applySorting($query, $request, $table);

        $page = $query->paginate($perPage);

        return $this->successResponse([
            'data' => $page->items(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ], 'OK');
    }

    public function store(CetaResourceRequest $request): JsonResponse
    {
        $table = $this->table((string) $request->route('resource'));
        $payload = $this->payload($request, $table);
        $payload = $this->withDefaults($payload, $table);
        $this->beforeStore($table, $payload);

        try {
            $id = DB::table($table)->insertGetId($payload);
            $this->afterStore($table, $id, $payload);

            return $this->successResponse($this->findRow($table, $id), 'Created', 201);
        } catch (\Throwable $e) {
            return $this->errorResponse(__('api.database_error'), 500, [
                'error' => $e->getMessage(),
                'table' => $table,
                'payload' => config('app.debug') ? $payload : null,
            ]);
        }
    }

    public function show(Request $request): JsonResponse
    {
        $table = $this->table((string) $request->route('resource'));
        $row = $this->findScopedRow($table, (string) $request->route('id'));

        return $this->successResponse($row, 'OK');
    }

    public function update(CetaResourceRequest $request): JsonResponse
    {
        $table = $this->table((string) $request->route('resource'));
        $id = (string) $request->route('id');
        $payload = $this->payload($request, $table, partial: true);

        // R11: Prevent updating auto-generated codes
        $this->preventCodeUpdate($table, $payload);

        if ($payload !== []) {
            $payload['updated_at'] = now();
            $this->scopedQuery($table)->where('id', $id)->update($payload);
        }

        return $this->successResponse($this->findScopedRow($table, $id), 'Updated');
    }

    public function destroy(Request $request): JsonResponse
    {
        $table = $this->table((string) $request->route('resource'));
        $id = (string) $request->route('id');

        // R08: Cannot delete customer if has active trips
        if ($table === 'customers') {
            $hasTrips = DB::table('trips')
                ->where('customer_id', $id)
                ->where('company_id', $this->companyId())
                ->where('status', '!=', 'completed')
                ->where('status', '!=', 'cancelled')
                ->exists();
            if ($hasTrips) {
                abort(422, 'Cannot delete customer with active trips');
            }
        }

        if (Schema::hasColumn($table, 'deleted_at')) {
            $this->scopedQuery($table)->where('id', $id)->update(['deleted_at' => now(), 'updated_at' => now()]);
        } else {
            $this->scopedQuery($table)->where('id', $id)->delete();
        }

        return $this->successResponse(null, 'Deleted');
    }

    public function nestedIndex(Request $request): JsonResponse
    {
        $child = (string) $request->route('child');
        $table = $this->table($child);
        $query = $this->scopedQuery($table)->where($this->foreignKey((string) $request->route('parent')), $request->route('id'));

        $this->applySearch($query, $request, $table);
        $this->applyFilters($query, $request, $table);
        $this->applySorting($query, $request, $table);

        if ($request->has('page') || $request->has('per_page')) {
            $perPage = max(1, min(100, (int) $request->query('per_page', 15)));
            $page = $query->paginate($perPage);

            return $this->successResponse([
                'data' => $page->items(),
                'meta' => [
                    'current_page' => $page->currentPage(),
                    'last_page' => $page->lastPage(),
                    'per_page' => $page->perPage(),
                    'total' => $page->total(),
                ],
            ], 'OK');
        }

        return $this->successResponse($query->get(), 'OK');
    }

    public function nestedStore(CetaResourceRequest $request): JsonResponse
    {
        $child = (string) $request->route('child');
        $table = $this->table($child);
        $payload = $this->payload($request, $table);
        $payload[$this->foreignKey((string) $request->route('parent'))] = $request->route('id');
        $payload = $this->withDefaults($payload, $table);
        $this->beforeStore($table, $payload);

        try {
            $id = DB::table($table)->insertGetId($payload);
            $this->afterStore($table, $id, $payload);

            return $this->successResponse($this->findRow($table, $id), 'Created', 201);
        } catch (\Throwable $e) {
            return $this->errorResponse(__('api.database_error'), 500, [
                'error' => $e->getMessage(),
                'table' => $table,
                'payload' => config('app.debug') ? $payload : null,
            ]);
        }
    }

    public function nestedUpdate(CetaResourceRequest $request): JsonResponse
    {
        $child = (string) $request->route('child');
        $table = $this->table($child);
        $id = (string) ($request->route('childId') ?? $request->route('itemId') ?? $request->route('docId') ?? $request->route('stopId') ?? $request->route('surId'));

        // R07: Prevent updating locked reconciliations
        if ($table === 'reconciliation_items') {
            $locked = DB::table('reconciliation_sessions')
                ->where('id', DB::table('reconciliation_items')->where('id', $id)->value('reconciliation_session_id'))
                ->where('locked_at', '!=', null)
                ->exists();
            if ($locked) {
                abort(422, 'Cannot update items in locked reconciliation');
            }
        }

        $payload = $this->payload($request, $table, partial: true);
        $payload['updated_at'] = now();
        $this->scopedQuery($table)->where('id', $id)->update($payload);

        return $this->successResponse($this->findScopedRow($table, $id), 'Updated');
    }

    public function nestedDestroy(Request $request): JsonResponse
    {
        $child = (string) $request->route('child');
        $table = $this->table($child);
        $id = (string) ($request->route('childId') ?? $request->route('itemId') ?? $request->route('docId') ?? $request->route('stopId') ?? $request->route('surId'));
        if (Schema::hasColumn($table, 'deleted_at')) {
            $this->scopedQuery($table)->where('id', $id)->update(['deleted_at' => now(), 'updated_at' => now()]);
        } else {
            $this->scopedQuery($table)->where('id', $id)->delete();
        }

        return $this->successResponse(null, 'Deleted');
    }

    public function action(CetaResourceRequest $request): JsonResponse
    {
        $resource = (string) $request->route('resource');
        $table = $this->table($resource);
        $id = (string) ($request->route('childId') ?? $request->route('id'));
        $action = (string) $request->route('actionName');

        if ($table === 'notifications' && $id === 'all' && $action === 'read') {
            $this->scopedQuery($table)->update(['read_at' => now(), 'updated_at' => now()]);

            return $this->successResponse(null, 'OK');
        }

        if ($table === 'trips') {
            $this->tripService->assertTripTransition($id, $action);

            // R02, R03, R12: Validate trip assignment constraints
            if ($action === 'assign') {
                $this->tripService->validateTripAssignment($id, $request);
            }
        }

        $updates = $this->actionUpdates($table, $action, $request);
        if ($updates !== []) {
            $updates['updated_at'] = now();
            $this->scopedQuery($table)->where('id', $id)->update($updates);
        }

        if ($action === 'email') {
            return $this->successResponse(null, "Email sent successfully to {$request->input('email', 'customer')}");
        }

        return $this->successResponse([
            'resource' => $resource,
            'action' => $action,
            'record' => $this->findScopedRow($table, $id),
        ], 'OK');
    }

    public function releaseVehicleAssignment(Request $request): JsonResponse
    {
        $this->fleetService->releaseVehicleAssignment(
            (string) $request->route('id'),
            $request->input('release_date', now()->toDateString()),
            $request->input('release_reason')
        );

        return $this->successResponse(null, 'Released');
    }

    public function available(Request $request): JsonResponse
    {
        $resource = (string) $request->route('resource');
        $table = $this->table($resource);
        $date = (string) $request->query('date', now()->toDateString());

        return $this->successResponse($this->fleetService->getAvailableResources($table, $date), 'OK');
    }

    public function priceLookup(Request $request): JsonResponse
    {
        return $this->successResponse($this->financeService->priceLookup($request), 'OK');
    }

    public function debtOverview(Request $request): JsonResponse
    {
        return $this->successResponse($this->financeService->debtOverview(), 'OK');
    }

    public function report(Request $request): JsonResponse
    {
        $type = (string) $request->route('reportType');
        $userId = $request->user()?->id;

        return $this->successResponse($this->reportService->getReportData($type, $userId), 'OK');
    }

    public function dispatch(Request $request): JsonResponse
    {
        $date = (string) $request->query('date', now()->toDateString());

        return $this->successResponse($this->reportService->getDispatchData($date), 'OK');
    }

    public function uploadDelete(Request $request): JsonResponse
    {
        return $this->successResponse(null, 'Deleted');
    }

    private function table(string $resource): string
    {
        $table = self::TABLES[$resource] ?? $resource;
        abort_unless(Schema::hasTable($table), 404, "Table {$table} not found");

        return $table;
    }

    private function scopedQuery(string $table): \Illuminate\Database\Query\Builder
    {
        $query = DB::table($table);
        if (Schema::hasColumn($table, 'deleted_at')) {
            $query->whereNull("{$table}.deleted_at");
        }
        if (Schema::hasColumn($table, 'company_id')) {
            $query->where("{$table}.company_id", $this->companyId());
        }
        if ($table === 'notifications') {
            $query->where('notifiable_id', request()->user()?->id);
        }

        return $query;
    }

    private function findScopedRow(string $table, string $id): object
    {
        $row = $this->scopedQuery($table)->where("{$table}.id", $id)->first();
        abort_if($row === null, 404, 'Not found');

        return $row;
    }

    private function findRow(string $table, int|string $id): ?object
    {
        return DB::table($table)->where('id', $id)->first();
    }

    private function companyId(): int
    {
        $companyId = $this->tenantContext->getCompanyId();
        if ($companyId !== null && $companyId > 0) {
            return $companyId;
        }

        $driverId = request()->user()?->driver_id;
        if ($driverId !== null) {
            $resolved = DB::table('drivers')->where('id', $driverId)->value('company_id');
            if ($resolved !== null) {
                return (int) $resolved;
            }
        }

        abort(403, 'Không thể xác định company_id.');
    }

    /** @return array<string, mixed> */
    private function payload(Request $request, string $table, bool $partial = false): array
    {
        $columns = collect(Schema::getColumnListing($table))->flip();
        $payload = array_intersect_key($request->all(), $columns->all());
        unset($payload['id'], $payload['created_at'], $payload['updated_at'], $payload['deleted_at']);

        if (isset($payload['password'])) {
            $payload['password'] = Hash::make((string) $payload['password']);
        }
        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $payload[$key] = json_encode($value, JSON_UNESCAPED_UNICODE);
            }
        }

        return $partial ? $payload : $this->withRequiredDefaults($payload, $table);
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    private function withDefaults(array $payload, string $table): array
    {
        if (Schema::hasColumn($table, 'company_id')) {
            $payload['company_id'] = $this->companyId();
        }
        if (Schema::hasColumn($table, 'created_by') && empty($payload['created_by'])) {
            $payload['created_by'] = request()->user()?->id;
        }
        if (Schema::hasColumn($table, 'uploaded_by') && empty($payload['uploaded_by'])) {
            $payload['uploaded_by'] = request()->user()?->id;
        }
        if (Schema::hasColumn($table, 'notifiable_id') && empty($payload['notifiable_id'])) {
            $payload['notifiable_id'] = request()->user()?->id;
        }
        if (Schema::hasColumn($table, 'notifiable_type') && empty($payload['notifiable_type'])) {
            $payload['notifiable_type'] = \App\Models\User::class;
        }
        if (Schema::hasColumn($table, 'created_at')) {
            $payload['created_at'] ??= now();
        }
        if (Schema::hasColumn($table, 'updated_at')) {
            $payload['updated_at'] ??= now();
        }

        return $payload;
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    private function withRequiredDefaults(array $payload, string $table): array
    {
        foreach (Schema::getColumns($table) as $column) {
            $field = $column['name'];
            if (isset($payload[$field]) || $field === 'id' || ! empty($column['auto_increment'])) {
                continue;
            }
            if (! empty($column['nullable']) || $column['default'] !== null || in_array($field, ['created_at', 'updated_at', 'deleted_at'], true)) {
                continue;
            }
            $payload[$field] = $this->defaultValue($table, $field, (string) $column['type']);
        }

        return $payload;
    }

    private function defaultValue(string $table, string $field, string $type): mixed
    {
        if ($field === 'id' && str_contains($type, 'char')) {
            return (string) Str::uuid();
        }
        if (str_ends_with($field, '_id')) {
            return match ($field) {
                'company_id' => $this->companyId(),
                'customer_id' => (int) (DB::table('customers')->where('company_id', $this->companyId())->value('id') ?? 1),
                'driver_id' => (int) (DB::table('drivers')->where('company_id', $this->companyId())->value('id') ?? 1),
                'vehicle_id' => (int) (DB::table('vehicles')->where('company_id', $this->companyId())->value('id') ?? 1),
                'spare_part_id' => (int) (DB::table('spare_parts')->where('company_id', $this->companyId())->value('id') ?? 1),
                'uploaded_by', 'requested_by', 'notifiable_id' => (int) request()->user()?->id,
                default => 1,
            };
        }
        if (str_starts_with($type, 'enum(')) {
            preg_match_all("/'([^']+)'/", $type, $matches);

            return $matches[1][0] ?? 'active';
        }
        if (str_contains($type, 'int')) {
            return str_contains($field, 'year') ? (int) now()->year : 1;
        }
        if (str_contains($type, 'decimal')) {
            return 1;
        }
        if (str_contains($type, 'date') || str_contains($type, 'timestamp')) {
            return now();
        }
        if (str_contains($type, 'time')) {
            return str_contains($field, 'end') ? '17:00:00' : '08:00:00';
        }
        if (str_contains($type, 'json')) {
            return json_encode([]);
        }

        return match (true) {
            str_contains($field, 'email') => 'seed+'.Str::lower(Str::random(6)).'@example.test',
            str_contains($field, 'password') => Hash::make('password'),
            str_contains($field, 'code') => 'CETA-'.Str::upper(Str::random(6)),
            str_contains($field, 'phone') => '0900000000',
            str_contains($field, 'url') => 'https://example.test/file',
            default => 'Seed '.$table.' '.$field,
        };
    }

    private function foreignKey(string $resource): string
    {
        return match ($resource) {
            'customers' => 'customer_id',
            'drivers' => 'driver_id',
            'vehicles' => 'vehicle_id',
            'trips' => 'trip_id',
            'price-lists' => 'price_list_id',
            'reconciliations' => 'session_id',
            default => rtrim(str_replace('-', '_', $resource), 's').'_id',
        };
    }

    private function orderColumn(string $table): string
    {
        return Schema::hasColumn($table, 'created_at') ? 'created_at' : 'id';
    }

    private function applyFilters(\Illuminate\Database\Query\Builder $query, Request $request, string $table): void
    {
        foreach ($request->query() as $key => $value) {
            if (in_array($key, ['page', 'per_page', 'q', 'search', 'keyword', 'sort_by', 'sort_order'], true) || $value === null || $value === '') {
                continue;
            }
            if (Schema::hasColumn($table, $key)) {
                $query->where("{$table}.{$key}", $value);
            }
        }
    }

    private function applySorting(\Illuminate\Database\Query\Builder $query, Request $request, string $table): void
    {
        $sortBy = (string) $request->query('sort_by', $this->orderColumn($table));
        $sortOrder = (string) $request->query('sort_order', 'desc');

        if (Schema::hasColumn($table, $sortBy)) {
            $query->orderBy("{$table}.{$sortBy}", $sortOrder === 'asc' ? 'asc' : 'desc');
        } else {
            $query->orderByDesc($this->orderColumn($table));
        }
    }

    private function applySearch(\Illuminate\Database\Query\Builder $query, Request $request, string $table): void
    {
        $term = (string) ($request->query('keyword', $request->query('q', $request->query('search', ''))));
        if ($term === '') {
            return;
        }
        $columns = array_values(array_intersect(['name', 'code', 'email', 'phone', 'address', 'plate_number'], Schema::getColumnListing($table)));
        $query->where(function ($q) use ($columns, $term, $table): void {
            foreach ($columns as $column) {
                $q->orWhere("{$table}.{$column}", 'like', "%{$term}%");
            }
        });
    }

    /** @return array<string, mixed> */
    private function actionUpdates(string $table, string $action, Request $request): array
    {
        $updates = match ($action) {
            'approve' => $this->statusUpdate($table, 'approved', $request),
            'confirm' => $this->statusUpdate($table, $table === 'reconciliation_sessions' ? 'confirmed' : 'approved', $request),
            'reject' => $this->statusUpdate($table, 'rejected', $request),
            'cancel' => $this->statusUpdate($table, 'cancelled', $request),
            'issue' => $this->statusUpdate($table, 'issued', $request) + ['issued_at' => now()],
            'mark-paid' => $this->statusUpdate($table, 'paid', $request) + ['paid_at' => now()],
            'submit' => $this->statusUpdate($table, 'submitted', $request),
            'lock' => $this->statusUpdate($table, 'locked', $request),
            'start' => $this->statusUpdate($table, 'in_progress', $request) + ['start_time' => now()],
            'assign' => $this->statusUpdate($table, 'in_progress', $request) + (Schema::hasColumn($table, 'assigned_at') ? ['assigned_at' => now()] : []),
            'deliver' => $this->statusUpdate($table, 'in_progress', $request) + (Schema::hasColumn($table, 'end_time') ? ['end_time' => now()] : []) + (Schema::hasColumn($table, 'actual_delivered_at') ? ['actual_delivered_at' => now()] : []),
            'complete' => $this->statusUpdate($table, 'completed', $request) + (Schema::hasColumn($table, 'end_time') ? ['end_time' => now()] : []),
            'arrive' => $this->statusUpdate($table, 'in_progress', $request) + (Schema::hasColumn($table, 'actual_delivered_at') ? ['actual_delivered_at' => now()] : []),
            'read' => Schema::hasColumn($table, 'read_at') ? ['read_at' => now()] : [],
            'email' => [],
            'status' => ['status' => $request->input('status', 'active')],
            default => $this->payload($request, $table, partial: true),
        };

        return array_filter(
            $updates,
            static fn (mixed $value, string $field): bool => Schema::hasColumn($table, $field),
            ARRAY_FILTER_USE_BOTH
        );
    }

    /** @return array<string, mixed> */
    private function statusUpdate(string $table, string $status, Request $request): array
    {
        $updates = [];
        if (Schema::hasColumn($table, 'status')) {
            $updates['status'] = $status;
        }
        foreach (['approved_by', 'reviewed_by', 'confirmed_by'] as $field) {
            if (Schema::hasColumn($table, $field)) {
                $updates[$field] = request()->user()?->id;
            }
        }
        foreach (['approved_at', 'reviewed_at', 'confirmed_at'] as $field) {
            if (Schema::hasColumn($table, $field)) {
                $updates[$field] = now();
            }
        }
        foreach (['rejection_reason', 'cancellation_reason', 'review_note', 'notes'] as $field) {
            if (Schema::hasColumn($table, $field) && $request->filled($field)) {
                $updates[$field] = $request->input($field);
            }
        }

        return $updates;
    }

    /** @param array<string, mixed> $payload */
    private function afterStore(string $table, int|string $id, array $payload): void
    {
        $this->financeService->afterStore($table, $id, $payload);
    }

    /** @param array<string, mixed> $payload */
    private function beforeStore(string $table, array $payload): void
    {
        $this->fleetService->beforeStore($table, $payload);
    }

    private function preventCodeUpdate(string $table, array &$payload): void
    {
        $codeFields = ['code', 'order_code'];
        foreach ($codeFields as $field) {
            if (isset($payload[$field])) {
                unset($payload[$field]);
            }
        }
    }
}
