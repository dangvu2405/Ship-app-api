<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * P0: Thêm company_id vào invoices.
 * Backfill từ customers.company_id, sau đó enforce NOT NULL.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('invoices', 'company_id')) {
            return;
        }

        Schema::table('invoices', function (Blueprint $table): void {
            $table->unsignedBigInteger('company_id')->nullable()->after('code');
            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
        });

        // Backfill từ customer (subquery syntax — works on MySQL and SQLite)
        DB::statement('
            UPDATE invoices
            SET company_id = (
                SELECT customers.company_id FROM customers WHERE customers.id = invoices.customer_id
            )
            WHERE company_id IS NULL
        ');

        // Fallback: backfill từ trip nếu customer không có company_id
        DB::statement('
            UPDATE invoices
            SET company_id = (
                SELECT trips.company_id FROM trips WHERE trips.id = invoices.trip_id
            )
            WHERE company_id IS NULL AND trip_id IS NOT NULL
        ');

        if (DB::table('invoices')->whereNull('company_id')->exists()) {
            throw new \RuntimeException('P0: invoices.company_id still NULL after backfill — fix data before retrying.');
        }

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE `invoices` MODIFY `company_id` BIGINT UNSIGNED NOT NULL');
        } else {
            Schema::table('invoices', function (Blueprint $table): void {
                $table->unsignedBigInteger('company_id')->nullable(false)->change();
            });
        }

        Schema::table('invoices', function (Blueprint $table): void {
            $table->index(['company_id', 'status'], 'idx_invoices_company_status');
            $table->index(['company_id', 'issued_at'], 'idx_invoices_company_issued_at');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('invoices', 'company_id')) {
            return;
        }

        Schema::table('invoices', function (Blueprint $table): void {
            try {
                $table->dropIndex('idx_invoices_company_issued_at');
            } catch (\Throwable) {
            }
            try {
                $table->dropIndex('idx_invoices_company_status');
            } catch (\Throwable) {
            }
        });

        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropForeign(['company_id']);
            $table->dropColumn('company_id');
        });
    }
};
