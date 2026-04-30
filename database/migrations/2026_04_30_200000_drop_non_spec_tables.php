<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drops bloat tables NOT defined in the USE_CASE_DIAGRAM_SPEC.
 * KEPT intentionally: org structure (offices/departments/positions),
 * RBAC (roles/permissions/role_permissions/user_roles/user_companies), logging (login_logs/export_logs).
 * Non-reversible — do not rollback on a live database.
 */
return new class extends Migration
{
    /** Tables in spec (must NOT be dropped). */
    private const SPEC_TABLES = [
        'companies', 'users', 'user_permissions', 'audit_logs',
        'vehicle_types', 'cargo_types', 'locations', 'route_templates',
        'cost_categories', 'order_status_configs',
        'customer_groups', 'customers', 'price_lists', 'price_list_items',
        'vehicles', 'vehicle_documents', 'vehicle_assignments',
        'spare_parts', 'maintenance_schedules', 'maintenance_records',
        'driver_teams', 'drivers', 'driver_documents',
        'leave_types', 'leave_requests', 'driver_work_schedules',
        'trips', 'trip_stops', 'trip_surcharges', 'trip_documents', 'trip_status_histories',
        'trip_costs', 'cost_approval_requests',
        'reconciliation_sessions', 'reconciliation_items', 'payment_records',
        'invoices', 'invoice_status_histories',
        'notifications', 'chat_messages', 'knowledge_articles', 'rag_index', 'report_caches',
        // Laravel / Sanctum system tables — keep
        'users', 'sessions', 'cache', 'cache_locks',
        'jobs', 'job_batches', 'failed_jobs',
        'personal_access_tokens', 'refresh_tokens', 'password_reset_tokens',
        'migrations',
    ];

    public function up(): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        Schema::disableForeignKeyConstraints();

        // ── 1. Drop non-spec columns (only those with no retained table dependency) ──
        $this->dropDriverNonSpecColumns();
        $this->dropVehicleNonSpecColumns();
        // driver_work_schedules.office_id kept — offices table is retained

        // ── 2. Drop bloat tables ───────────────────────────────────────────────
        // KEPT: offices, departments, positions, roles, permissions, role_permissions,
        //       user_roles, user_companies, login_logs, export_logs
        $toDrop = [
            // violations
            'violation_disputes',
            'violations',
            // leave / attendance
            'leave_balances',
            'overtime_requests',
            'night_shift_policies',
            'public_holidays',
            // schedule
            'office_schedule_applications',
            'work_schedule_templates',
            // vehicle
            'vehicle_images',
            'vehicle_expenses',
            // trips extras
            'trip_bonus_rules',
            // payroll (all variants)
            'payroll_adjustments',
            'payroll_lines',
            'payrolls',
            // attendance
            'attendances',
            // accounting modules not in spec
            'journal_entry_lines',
            'journal_entries',
            'chart_of_accounts',
            'insurance_rates',
            'tax_brackets',
        ];

        foreach ($toDrop as $table) {
            Schema::dropIfExists($table);
        }

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        // Intentionally empty — restoring dropped tables requires a full seed.
    }

    // ─── helpers ──────────────────────────────────────────────────────────────

    private function dropDriverNonSpecColumns(): void
    {
        if (! Schema::hasTable('drivers')) {
            return;
        }

        // office_id / department_id / position_id KEPT — org structure tables are retained
        $extraColumns = [
            'health_insurance_no', 'insurance_registered_at',
            'identity_image_url', 'driver_insurance_no', 'driver_insurance_expired_date',
        ];
        $toDrop = array_filter($extraColumns, fn ($c) => Schema::hasColumn('drivers', $c));

        if (! empty($toDrop)) {
            try {
                Schema::table('drivers', function (Blueprint $table) use ($toDrop): void {
                    $table->dropColumn(array_values($toDrop));
                });
            } catch (\Throwable) {
            }
        }
    }

    private function dropVehicleNonSpecColumns(): void
    {
        if (! Schema::hasTable('vehicles')) {
            return;
        }

        // office_id KEPT — offices table is retained
        $extra = ['image_front', 'image_back', 'image_side', 'image_other'];
        $toDrop = array_filter($extra, fn ($c) => Schema::hasColumn('vehicles', $c));

        if (! empty($toDrop)) {
            try {
                Schema::table('vehicles', function (Blueprint $table) use ($toDrop): void {
                    $table->dropColumn(array_values($toDrop));
                });
            } catch (\Throwable) {
            }
        }
    }

    private function safeDropForeign(string $table, string $column): void
    {
        try {
            Schema::table($table, function (Blueprint $t) use ($column): void {
                $t->dropForeign([$column]);
            });
        } catch (\Throwable) {
        }
    }
};
