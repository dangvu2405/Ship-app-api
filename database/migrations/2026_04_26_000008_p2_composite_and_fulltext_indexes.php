<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * P2: Thêm composite indexes cho báo cáo + FULLTEXT indexes cho search.
 *
 * Composite indexes:
 *   - trips(company_id, start_time, status)       — báo cáo doanh thu theo kỳ
 *   - payroll_lines(company_id, driver_id, payroll_id) — báo cáo lương
 *   - audit_logs(company_id, created_at)           — audit log theo công ty + thời gian
 *   - violations(company_id, occurred_at)          — vi phạm theo thời gian
 *   - vehicle_expenses(company_id, expense_date, type) — chi phí xe theo tháng
 *
 * FULLTEXT indexes (MySQL only):
 *   - drivers(name, code, phone, email)
 *   - vehicles(plate_number, brand, model)
 *   - customers(name, phone, email, tax_code)
 */
return new class extends Migration
{
    public function up(): void
    {
        $isMysql = Schema::getConnection()->getDriverName() === 'mysql';

        Schema::table('trips', function (Blueprint $table): void {
            if (! $this->indexExists('trips', 'idx_trips_company_start_time')) {
                $table->index(['company_id', 'start_time', 'status'], 'idx_trips_company_start_time');
            }
        });

        Schema::table('payroll_lines', function (Blueprint $table): void {
            if (! $this->indexExists('payroll_lines', 'idx_pl_company_driver')) {
                $table->index(['company_id', 'driver_id', 'payroll_id'], 'idx_pl_company_driver');
            }
        });

        Schema::table('audit_logs', function (Blueprint $table): void {
            if (! $this->indexExists('audit_logs', 'idx_audit_company_created')) {
                $table->index(['company_id', 'created_at'], 'idx_audit_company_created');
            }
        });

        if (Schema::hasTable('violations')) {
            Schema::table('violations', function (Blueprint $table): void {
                if (! $this->indexExists('violations', 'idx_viol_company_occurred')) {
                    $table->index(['company_id', 'occurred_at'], 'idx_viol_company_occurred');
                }
            });
        }

        Schema::table('vehicle_expenses', function (Blueprint $table): void {
            if (! $this->indexExists('vehicle_expenses', 'idx_ve_company_date_type')) {
                $table->index(['company_id', 'expense_date', 'type'], 'idx_ve_company_date_type');
            }
        });

        // FULLTEXT (MySQL only — SQLite/PgSQL không cần check)
        if ($isMysql) {
            $this->safeStatement("ALTER TABLE `drivers` ADD FULLTEXT KEY `ft_drivers_search` (name, code, phone, email)");
            $this->safeStatement("ALTER TABLE `vehicles` ADD FULLTEXT KEY `ft_vehicles_search` (plate_number, brand, model)");
            $this->safeStatement("ALTER TABLE `customers` ADD FULLTEXT KEY `ft_customers_search` (name, phone, email, tax_code)");
        }
    }

    public function down(): void
    {
        $isMysql = Schema::getConnection()->getDriverName() === 'mysql';

        if ($isMysql) {
            $this->safeStatement('ALTER TABLE `customers` DROP INDEX `ft_customers_search`');
            $this->safeStatement('ALTER TABLE `vehicles` DROP INDEX `ft_vehicles_search`');
            $this->safeStatement('ALTER TABLE `drivers` DROP INDEX `ft_drivers_search`');
        }

        Schema::table('vehicle_expenses', function (Blueprint $table): void {
            try { $table->dropIndex('idx_ve_company_date_type'); } catch (\Throwable) {}
        });

        if (Schema::hasTable('violations')) {
            Schema::table('violations', function (Blueprint $table): void {
                try { $table->dropIndex('idx_viol_company_occurred'); } catch (\Throwable) {}
            });
        }

        Schema::table('audit_logs', function (Blueprint $table): void {
            try { $table->dropIndex('idx_audit_company_created'); } catch (\Throwable) {}
        });

        Schema::table('payroll_lines', function (Blueprint $table): void {
            try { $table->dropIndex('idx_pl_company_driver'); } catch (\Throwable) {}
        });

        Schema::table('trips', function (Blueprint $table): void {
            try { $table->dropIndex('idx_trips_company_start_time'); } catch (\Throwable) {}
        });
    }

    private function indexExists(string $table, string $indexName): bool
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return false;
        }
        try {
            return count(DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$indexName])) > 0;
        } catch (\Throwable) {
            return false;
        }
    }

    private function safeStatement(string $sql): void
    {
        try {
            DB::statement($sql);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('[composite_indexes] Skipped: ' . $e->getMessage());
        }
    }
};
