<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        $duplicates = DB::table('roles')
            ->select('name', DB::raw('COUNT(*) as cnt'))
            ->whereNull('company_id')
            ->groupBy('name')
            ->having('cnt', '>', 1)
            ->pluck('name');

        if ($duplicates->isNotEmpty()) {
            throw new RuntimeException(
                'Cannot enforce global role uniqueness. Duplicate global role names: '.$duplicates->implode(', ')
            );
        }

        // Drop FK before adding stored generated column (MySQL restriction with CASCADE FKs)
        try {
            DB::statement('ALTER TABLE `roles` DROP FOREIGN KEY `roles_company_id_foreign`');
        } catch (\Throwable) {
        }

        if (! Schema::hasColumn('roles', 'company_scope_id')) {
            DB::statement('ALTER TABLE `roles` ADD COLUMN `company_scope_id` BIGINT UNSIGNED AS (IFNULL(company_id, 0)) STORED AFTER `company_id`');
        }

        // Recreate FK
        try {
            DB::statement('ALTER TABLE `roles` ADD CONSTRAINT `roles_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE');
        } catch (\Throwable) {
        }

        if (! $this->indexExists('roles', 'roles_name_company_scope_unique')) {
            DB::statement('CREATE UNIQUE INDEX roles_name_company_scope_unique ON roles (name, company_scope_id)');
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        if ($this->indexExists('roles', 'roles_name_company_scope_unique')) {
            DB::statement('DROP INDEX roles_name_company_scope_unique ON roles');
        }

        Schema::table('roles', function (Blueprint $table): void {
            if (Schema::hasColumn('roles', 'company_scope_id')) {
                $table->dropColumn('company_scope_id');
            }
        });
    }

    private function indexExists(string $table, string $indexName): bool
    {
        return DB::table('information_schema.STATISTICS')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', $table)
            ->where('INDEX_NAME', $indexName)
            ->exists();
    }
};
