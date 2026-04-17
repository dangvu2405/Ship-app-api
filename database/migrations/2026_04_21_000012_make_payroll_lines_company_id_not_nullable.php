<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tighten the payroll_lines.company_id FK.
 *
 * The column was originally created nullable with nullOnDelete, which could
 * produce orphaned lines (company_id = NULL) if the parent payroll was
 * deleted.  The model always populates company_id from the parent payroll
 * at creation time, so NULL values should never occur in practice.
 *
 * Changes:
 *   - Back-fill any orphaned rows from their parent payroll (safety net).
 *   - Drop any existing FK on company_id (idempotent for partial runs).
 *   - Ensure company_id is NOT NULL and re-add FK with cascadeOnDelete.
 */
return new class extends Migration
{
    private const TARGET_FK = 'payroll_lines_company_id_foreign';

    public function up(): void
    {
        if (! Schema::hasTable('payroll_lines') || ! Schema::hasColumn('payroll_lines', 'company_id')) {
            return;
        }

        // Align company_id with parent payroll for every row (fixes NULL, stale, or invalid values).
        DB::statement('
            UPDATE payroll_lines pl
            INNER JOIN payrolls p ON p.id = pl.payroll_id
            SET pl.company_id = p.company_id
        ');

        // Remove orphan lines that no longer have a parent payroll (cannot satisfy NOT NULL + FK).
        DB::statement('
            DELETE pl FROM payroll_lines pl
            LEFT JOIN payrolls p ON p.id = pl.payroll_id
            WHERE p.id IS NULL
        ');

        $this->dropForeignKeysOnCompanyId('payroll_lines', 'company_id');

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE `payroll_lines` MODIFY `company_id` BIGINT UNSIGNED NOT NULL');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE payroll_lines ALTER COLUMN company_id SET NOT NULL');
        } else {
            Schema::table('payroll_lines', function (Blueprint $table): void {
                $table->unsignedBigInteger('company_id')->nullable(false)->change();
            });
        }

        if (! $this->foreignKeyExists('payroll_lines', self::TARGET_FK)) {
            Schema::table('payroll_lines', function (Blueprint $table): void {
                $table->foreign('company_id')
                    ->references('id')
                    ->on('companies')
                    ->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('payroll_lines') || ! Schema::hasColumn('payroll_lines', 'company_id')) {
            return;
        }

        $this->dropForeignKeysOnCompanyId('payroll_lines', 'company_id');

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE `payroll_lines` MODIFY `company_id` BIGINT UNSIGNED NULL');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE payroll_lines ALTER COLUMN company_id DROP NOT NULL');
        } else {
            Schema::table('payroll_lines', function (Blueprint $table): void {
                $table->unsignedBigInteger('company_id')->nullable()->change();
            });
        }

        Schema::table('payroll_lines', function (Blueprint $table): void {
            $table->foreign('company_id')
                ->references('id')
                ->on('companies')
                ->nullOnDelete();
        });
    }

    private function dropForeignKeysOnCompanyId(string $table, string $column): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            $rows = DB::select(
                'SELECT CONSTRAINT_NAME AS name
                 FROM information_schema.KEY_COLUMN_USAGE
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = ?
                   AND COLUMN_NAME = ?
                   AND REFERENCED_TABLE_NAME IS NOT NULL',
                [$table, $column],
            );

            foreach ($rows as $row) {
                $name = (string) $row->name;
                DB::statement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$name}`");
            }

            return;
        }

        if ($driver === 'pgsql') {
            $rows = DB::select(
                'SELECT tc.constraint_name AS name
                 FROM information_schema.table_constraints AS tc
                 JOIN information_schema.key_column_usage AS kcu
                   ON tc.constraint_name = kcu.constraint_name
                  AND tc.table_schema = kcu.table_schema
                 WHERE tc.table_schema = current_schema()
                   AND tc.table_name = ?
                   AND tc.constraint_type = \'FOREIGN KEY\'
                   AND kcu.column_name = ?',
                [$table, $column],
            );

            foreach ($rows as $row) {
                $name = (string) $row->name;
                DB::statement("ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS \"{$name}\"");
            }
        }
    }

    private function foreignKeyExists(string $table, string $constraintName): bool
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            $row = DB::selectOne(
                'SELECT CONSTRAINT_NAME AS name
                 FROM information_schema.TABLE_CONSTRAINTS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = ?
                   AND CONSTRAINT_TYPE = \'FOREIGN KEY\'
                   AND CONSTRAINT_NAME = ?',
                [$table, $constraintName],
            );

            return $row !== null;
        }

        if ($driver === 'pgsql') {
            $row = DB::selectOne(
                'SELECT tc.constraint_name AS name
                 FROM information_schema.table_constraints AS tc
                 WHERE tc.table_schema = current_schema()
                   AND tc.table_name = ?
                   AND tc.constraint_type = \'FOREIGN KEY\'
                   AND tc.constraint_name = ?',
                [$table, $constraintName],
            );

            return $row !== null;
        }

        return false;
    }
};
