<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

final class CetaResourceRequest extends FormRequest
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
        'invoice-status-histories' => 'invoice_status_histories',
        'invoices' => 'invoices',
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
        'reconciliation-items' => 'reconciliation_items',
        'reconciliations' => 'reconciliation_sessions',
        'report-caches' => 'report_caches',
        'route-templates' => 'route_templates',
        'spare-parts' => 'spare_parts',
        'transport-requests' => 'transport_requests',
        'trip-costs' => 'trip_costs',
        'trip-documents' => 'trip_documents',
        'trip-stops' => 'trip_stops',
        'trip-surcharges' => 'trip_surcharges',
        'trips' => 'trips',
        'user-permissions' => 'user_permissions',
        'users' => 'users',
        'vehicle-assignments' => 'vehicle_assignments',
        'vehicle-documents' => 'vehicle_documents',
        'vehicle-types' => 'vehicle_types',
        'vehicles' => 'vehicles',
        'work-schedules' => 'driver_work_schedules',
    ];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * Keep validation schema-driven while the CETA controllers are being split by domain.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $table = $this->table();
        $rules = [];

        if ($table !== null && Schema::hasTable($table)) {
            foreach ($this->all() as $field => $value) {
                if (! is_string($field) || ! Schema::hasColumn($table, $field)) {
                    $rules[$field] = ['prohibited'];
                }
            }
        }

        return array_merge($rules, $this->domainRules($table));
    }

    /**
     * @return array<string, mixed>
     */
    private function domainRules(?string $table): array
    {
        return match ($table) {
            'users' => [
                'email' => ['sometimes', 'email'],
                'role' => ['sometimes', Rule::in(['super_admin', 'admin', 'dispatcher', 'accountant', 'viewer'])],
                'status' => ['sometimes', Rule::in(['active', 'inactive'])],
            ],
            'trips' => [
                'status' => ['sometimes', Rule::in(['pending', 'in_progress', 'completed', 'cancelled'])],
                'payment_status' => ['sometimes', Rule::in(['unpaid', 'invoiced', 'paid'])],
            ],
            'vehicles' => [
                'status' => ['sometimes', Rule::in(['active', 'maintenance', 'inactive', 'broken'])],
            ],
            'drivers' => [
                'available_status' => ['sometimes', Rule::in(['available', 'busy', 'offline'])],
                'status' => ['sometimes', Rule::in(['active', 'inactive', 'resigned'])],
            ],
            'leave_requests', 'trip_costs', 'cost_approval_requests' => [
                'status' => ['sometimes', Rule::in(['pending', 'approved', 'rejected', 'cancelled'])],
            ],
            default => [],
        };
    }

    private function table(): ?string
    {
        $resource = $this->route('child') ?? $this->route('resource');
        if (! is_string($resource) || $resource === '') {
            return null;
        }

        return self::TABLES[$resource] ?? str_replace('-', '_', $resource);
    }
}
