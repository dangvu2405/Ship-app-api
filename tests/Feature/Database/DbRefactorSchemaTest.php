<?php

declare(strict_types=1);

namespace Tests\Feature\Database;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class DbRefactorSchemaTest extends TestCase
{
    use RefreshDatabase;

    private const STRICT_SPEC_TABLES = [
        'companies', 'users', 'user_permissions', 'audit_logs',
        'vehicle_types', 'cargo_types', 'locations', 'route_templates', 'cost_categories', 'order_status_configs',
        'customer_groups', 'customers', 'price_lists', 'price_list_items',
        'vehicles', 'vehicle_documents', 'vehicle_assignments', 'spare_parts', 'maintenance_schedules', 'maintenance_records',
        'driver_teams', 'drivers', 'driver_documents',
        'leave_types', 'leave_requests', 'driver_work_schedules',
        'trips', 'trip_stops', 'trip_surcharges', 'trip_documents', 'trip_status_histories',
        'trip_costs', 'cost_approval_requests',
        'reconciliation_sessions', 'reconciliation_items', 'payment_records',
        'invoices', 'invoice_status_histories',
        'notifications', 'chat_messages', 'knowledge_articles', 'rag_index', 'report_caches',
    ];

    public function test_phase_1_3_tables_and_columns_exist(): void
    {
        $this->assertTrue(Schema::hasTable('trips'));
        $this->assertTrue(Schema::hasTable('trip_stops'));
        $this->assertTrue(Schema::hasTable('trip_surcharges'));
        $this->assertTrue(Schema::hasTable('trip_documents'));
        $this->assertTrue(Schema::hasTable('trip_costs'));
        $this->assertTrue(Schema::hasTable('cost_approval_requests'));
        $this->assertTrue(Schema::hasTable('reconciliation_sessions'));
        $this->assertTrue(Schema::hasTable('reconciliation_items'));
        $this->assertTrue(Schema::hasTable('payment_records'));

        $this->assertTrue(Schema::hasColumn('trips', 'contact_name'));
        $this->assertTrue(Schema::hasColumn('trips', 'cargo_type_id'));
        $this->assertTrue(Schema::hasColumn('trips', 'dispatcher_id'));
        $this->assertTrue(Schema::hasColumn('trips', 'base_price'));
        $this->assertTrue(Schema::hasColumn('trips', 'payment_status'));
        $this->assertTrue(Schema::hasColumn('leave_requests', 'company_id'));
    }

    public function test_strict_spec_tables_exist(): void
    {
        foreach (self::STRICT_SPEC_TABLES as $table) {
            $this->assertTrue(Schema::hasTable($table), "Missing strict spec table: {$table}");
        }
    }
}

