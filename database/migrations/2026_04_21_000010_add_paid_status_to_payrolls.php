<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Extend the payroll status workflow: draft → approved → locked → paid.
 *
 * Add paid_at and paid_by columns so there is an auditable record of
 * when salary was actually disbursed and by whom.
 */
return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement("ALTER TABLE payrolls MODIFY COLUMN status ENUM('draft','approved','locked','paid') NOT NULL DEFAULT 'draft'");
        } else {
            // SQLite / PostgreSQL: string column already supports any value
            Schema::table('payrolls', function (Blueprint $table): void {
                $table->string('status', 20)->default('draft')->change();
            });
        }

        Schema::table('payrolls', function (Blueprint $table): void {
            if (! Schema::hasColumn('payrolls', 'paid_at')) {
                $table->timestamp('paid_at')->nullable()->after('approved_at');
            }
            if (! Schema::hasColumn('payrolls', 'paid_by')) {
                $table->foreignId('paid_by')->nullable()->after('paid_at')
                    ->constrained('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('payrolls', function (Blueprint $table): void {
            if (Schema::hasColumn('payrolls', 'paid_by')) {
                $table->dropConstrainedForeignId('paid_by');
            }
            if (Schema::hasColumn('payrolls', 'paid_at')) {
                $table->dropColumn('paid_at');
            }
        });

        $driver = DB::connection()->getDriverName();
        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement("ALTER TABLE payrolls MODIFY COLUMN status ENUM('draft','approved','locked') NOT NULL DEFAULT 'draft'");
        }
    }
};
