<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * P0: Thêm UNIQUE constraint cho drivers.license_no và national_id_no.
 * Kiểm tra duplicate trước khi thêm constraint để tránh migration fail.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->checkNoDuplicates('drivers', 'license_no');
        $this->checkNoDuplicates('drivers', 'national_id_no');

        Schema::table('drivers', function (Blueprint $table): void {
            if (! $this->indexExists('drivers', 'uniq_drivers_license_no')) {
                $table->unique('license_no', 'uniq_drivers_license_no');
            }
            if (! $this->indexExists('drivers', 'uniq_drivers_national_id_no')) {
                $table->unique('national_id_no', 'uniq_drivers_national_id_no');
            }
        });
    }

    public function down(): void
    {
        Schema::table('drivers', function (Blueprint $table): void {
            try { $table->dropUnique('uniq_drivers_national_id_no'); } catch (\Throwable) {}
            try { $table->dropUnique('uniq_drivers_license_no'); } catch (\Throwable) {}
        });
    }

    private function checkNoDuplicates(string $table, string $column): void
    {
        $duplicates = DB::table($table)
            ->select($column, DB::raw('COUNT(*) as cnt'))
            ->whereNotNull($column)
            ->groupBy($column)
            ->having('cnt', '>', 1)
            ->get();

        if ($duplicates->isNotEmpty()) {
            $values = $duplicates->pluck($column)->implode(', ');
            throw new \RuntimeException(
                "P0: Cannot add UNIQUE on {$table}.{$column} — duplicates found: {$values}. Resolve data first."
            );
        }
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
};
