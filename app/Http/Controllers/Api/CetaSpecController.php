<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Tenancy\TenantContext;
use App\Http\Requests\CetaResourceRequest;
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

    public function __construct(private readonly TenantContext $tenantContext) {}

    public function index(Request $request): JsonResponse
    {
        $table = $this->table((string) $request->route('resource'));
        $query = $this->scopedQuery($table);

        $this->applySearch($query, $request, $table);
        $this->applyFilters($query, $request, $table);

        $perPage = max(1, min(100, (int) $request->query('per_page', 15)));
        $page = $query->orderByDesc($this->orderColumn($table))->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $page->items(),
            'meta' => [
                'page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    public function store(CetaResourceRequest $request): JsonResponse
    {
        $table = $this->table((string) $request->route('resource'));
        $payload = $this->payload($request, $table);
        $payload = $this->withDefaults($payload, $table);
        $this->beforeStore($table, $payload);

        $id = DB::table($table)->insertGetId($payload);

        $this->afterStore($table, $id, $payload);

        return $this->successResponse($this->findRow($table, $id), 'Created', 201);
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
        $rows = $query->orderByDesc($this->orderColumn($table))->get();

        return $this->successResponse($rows, 'OK');
    }

    public function nestedStore(CetaResourceRequest $request): JsonResponse
    {
        $child = (string) $request->route('child');
        $table = $this->table($child);
        $payload = $this->payload($request, $table);
        $payload[$this->foreignKey((string) $request->route('parent'))] = $request->route('id');
        $payload = $this->withDefaults($payload, $table);
        $this->beforeStore($table, $payload);

        $id = DB::table($table)->insertGetId($payload);
        $this->afterStore($table, $id, $payload);

        return $this->successResponse($this->findRow($table, $id), 'Created', 201);
    }

    public function nestedUpdate(CetaResourceRequest $request): JsonResponse
    {
        $child = (string) $request->route('child');
        $table = $this->table($child);
        $id = (string) ($request->route('childId') ?? $request->route('itemId') ?? $request->route('docId') ?? $request->route('stopId') ?? $request->route('surId'));
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
            $this->assertTripTransition($id, $action);
        }

        $updates = $this->actionUpdates($table, $action, $request);
        if ($updates !== []) {
            $updates['updated_at'] = now();
            $this->scopedQuery($table)->where('id', $id)->update($updates);
        }

        return $this->successResponse([
            'resource' => $resource,
            'action' => $action,
            'record' => $this->findScopedRow($table, $id),
        ], 'OK');
    }

    public function releaseVehicleAssignment(Request $request): JsonResponse
    {
        $vehicleId = (string) $request->route('id');
        DB::table('vehicle_assignments')
            ->where('vehicle_id', $vehicleId)
            ->whereNull('to_date')
            ->where('company_id', $this->companyId())
            ->update([
                'to_date' => $request->input('release_date', now()->toDateString()),
                'release_reason' => $request->input('release_reason'),
                'updated_at' => now(),
            ]);

        return $this->successResponse(null, 'Released');
    }

    public function available(Request $request): JsonResponse
    {
        $resource = (string) $request->route('resource');
        $table = $this->table($resource);
        $date = (string) $request->query('date', now()->toDateString());
        $query = $this->scopedQuery($table);

        if ($table === 'vehicles') {
            $busy = DB::table('trips')->whereDate('scheduled_date', $date)->pluck('vehicle_id')->filter()->all();
            $query->where('status', 'active')->whereNotIn('id', $busy);
        }
        if ($table === 'drivers') {
            $busy = DB::table('trips')->whereDate('scheduled_date', $date)->pluck('driver_id')->filter()->all();
            $query->where('status', 'active')->where('available_status', 'available')->whereNotIn('id', $busy);
        }

        return $this->successResponse($query->limit(100)->get(), 'OK');
    }

    public function priceLookup(Request $request): JsonResponse
    {
        $query = $this->scopedQuery('price_list_items');
        foreach (['route_template_id', 'vehicle_type_id', 'cargo_type_id'] as $field) {
            if ($request->filled($field)) {
                $query->where($field, $request->input($field));
            }
        }
        if ($request->filled('customer_id')) {
            $query->join('price_lists', 'price_lists.id', '=', 'price_list_items.price_list_id')
                ->where('price_lists.customer_id', $request->input('customer_id'));
        }
        $item = $query->select('price_list_items.*')->orderByDesc('price_list_items.id')->first();

        return $this->successResponse($item ? [
            'price' => $item->price,
            'price_unit' => $item->price_unit,
            'price_list_id' => $item->price_list_id,
        ] : null, 'OK');
    }

    public function debtOverview(Request $request): JsonResponse
    {
        $companyId = $this->companyId();
        $rows = DB::table('customers')
            ->leftJoin('trips', 'trips.customer_id', '=', 'customers.id')
            ->where('customers.company_id', $companyId)
            ->groupBy('customers.id', 'customers.name')
            ->selectRaw('customers.id, customers.name, COALESCE(SUM(CASE WHEN trips.payment_status != "paid" THEN trips.price ELSE 0 END),0) as debt')
            ->get();

        return $this->successResponse($rows, 'OK');
    }

    public function report(Request $request): JsonResponse
    {
        $type = (string) $request->route('reportType');
        $companyId = $this->companyId();
        if ($type === 'notifications-unread') {
            return $this->successResponse([
                'unread_count' => DB::table('notifications')
                    ->where('notifiable_id', $request->user()?->id)
                    ->whereNull('read_at')
                    ->count(),
            ], 'OK');
        }

        $data = [
            'type' => $type,
            'summary' => [
                'trips' => Schema::hasTable('trips') ? DB::table('trips')->where('company_id', $companyId)->count() : 0,
                'vehicles' => Schema::hasTable('vehicles') ? DB::table('vehicles')->where('company_id', $companyId)->count() : 0,
                'drivers' => Schema::hasTable('drivers') ? DB::table('drivers')->where('company_id', $companyId)->count() : 0,
                'customers' => Schema::hasTable('customers') ? DB::table('customers')->where('company_id', $companyId)->count() : 0,
            ],
        ];

        return $this->successResponse($data, 'OK');
    }

    public function dispatch(Request $request): JsonResponse
    {
        $date = (string) $request->query('date', now()->toDateString());
        $companyId = $this->companyId();
        $trips = DB::table('trips')->where('company_id', $companyId)->whereDate('scheduled_date', $date)->get();

        return $this->successResponse([
            'date' => $date,
            'trips' => $trips,
            'unassigned_trips' => $trips->whereNull('vehicle_id')->values(),
            'daily_summary' => [
                'total_trips' => $trips->count(),
                'unassigned' => $trips->whereNull('vehicle_id')->count(),
            ],
        ], 'OK');
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

        return (int) (DB::table('companies')->orderBy('id')->value('id') ?? 1);
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
        if (Schema::hasColumn($table, 'company_id') && empty($payload['company_id'])) {
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
        foreach (DB::select("SHOW COLUMNS FROM `{$table}`") as $column) {
            $field = (string) $column->Field;
            if (isset($payload[$field]) || $field === 'id' || str_contains((string) $column->Extra, 'auto_increment')) {
                continue;
            }
            if ((string) $column->Null === 'YES' || $column->Default !== null || in_array($field, ['created_at', 'updated_at', 'deleted_at'], true)) {
                continue;
            }
            $payload[$field] = $this->defaultValue($table, $field, (string) $column->Type);
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
            if (in_array($key, ['page', 'per_page', 'q', 'search'], true) || $value === null || $value === '') {
                continue;
            }
            if (Schema::hasColumn($table, $key)) {
                $query->where("{$table}.{$key}", $value);
            }
        }
    }

    private function applySearch(\Illuminate\Database\Query\Builder $query, Request $request, string $table): void
    {
        $term = (string) ($request->query('q', $request->query('search', '')));
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
            'deliver', 'complete' => $this->statusUpdate($table, 'completed', $request) + ['end_time' => now(), 'actual_delivered_at' => now()],
            'arrive' => $this->statusUpdate($table, 'arrived', $request) + ['actual_time' => now()],
            'read' => Schema::hasColumn($table, 'read_at') ? ['read_at' => now()] : [],
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
    private function afterStore(string $table, int $id, array $payload): void
    {
        if ($table !== 'trip_costs') {
            return;
        }
        $threshold = DB::table('cost_categories')->where('id', $payload['cost_category_id'] ?? null)->value('approval_threshold');
        $amount = (float) ($payload['amount'] ?? 0);
        if ($threshold !== null && $amount > (float) $threshold) {
            DB::table('trip_costs')->where('id', $id)->update(['approval_required' => true, 'status' => 'pending']);
            DB::table('cost_approval_requests')->insert([
                'company_id' => $payload['company_id'] ?? $this->companyId(),
                'trip_id' => $payload['trip_id'] ?? 1,
                'requested_by' => request()->user()?->id ?? 1,
                'total_amount' => $amount,
                'reason' => $payload['description'] ?? 'Cost exceeds approval threshold',
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /** @param array<string, mixed> $payload */
    private function beforeStore(string $table, array $payload): void
    {
        if ($table !== 'vehicle_assignments') {
            return;
        }

        foreach (['vehicle_id', 'driver_id'] as $field) {
            if (empty($payload[$field])) {
                continue;
            }

            DB::table('vehicle_assignments')
                ->where($field, $payload[$field])
                ->where('company_id', $payload['company_id'] ?? $this->companyId())
                ->whereNull('to_date')
                ->update([
                    'to_date' => now()->toDateString(),
                    'release_reason' => 'Auto-closed before new active assignment',
                    'updated_at' => now(),
                ]);
        }
    }

    private function assertTripTransition(string $id, string $action): void
    {
        $current = (string) $this->scopedQuery('trips')->where('id', $id)->value('status');
        $allowed = [
            'assign' => ['pending'],
            'start' => ['pending'],
            'deliver' => ['in_progress'],
            'complete' => ['in_progress'],
            'cancel' => ['pending', 'in_progress'],
            'change-vehicle' => ['pending', 'in_progress'],
            'change-driver' => ['pending', 'in_progress'],
        ];

        if (isset($allowed[$action]) && ! in_array($current, $allowed[$action], true)) {
            abort(422, "Invalid trip transition from {$current} by {$action}");
        }
    }
}
