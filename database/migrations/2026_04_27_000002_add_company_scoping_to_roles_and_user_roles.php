<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds multi-tenant RBAC scoping:
 *
 * roles.company_id (nullable):
 *   NULL  → Global Role (admin, system roles — apply across all companies)
 *   value → Company-specific Role (custom roles scoped to one tenant)
 *
 * user_roles.company_id (nullable):
 *   NULL  → user holds this global role regardless of active tenant
 *   value → user holds this role only within that company
 */
return new class extends Migration
{
    public function up(): void
    {
        // --- roles table ---
        // company_id may already exist from an earlier migration — skip if so.
        Schema::table('roles', function (Blueprint $table) {
            if (! Schema::hasColumn('roles', 'company_id')) {
                $table->foreignId('company_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('companies')
                    ->nullOnDelete();

                $table->index('company_id');
            }

            // Drop the old global unique on name (same role name can exist per company)
            if ($this->indexExists('roles', 'roles_name_unique')) {
                $table->dropUnique('roles_name_unique');
            }

            if (! $this->indexExists('roles', 'roles_name_company_unique')) {
                $table->unique(['name', 'company_id'], 'roles_name_company_unique');
            }
        });

        // --- user_roles table ---
        Schema::table('user_roles', function (Blueprint $table) {
            if (! Schema::hasColumn('user_roles', 'company_id')) {
                $table->foreignId('company_id')
                    ->nullable()
                    ->after('role_id')
                    ->constrained('companies')
                    ->nullOnDelete();

                $table->index('company_id');
            }

            if ($this->indexExists('user_roles', 'user_roles_user_id_role_id_unique')) {
                $table->dropUnique('user_roles_user_id_role_id_unique');
            }

            if (! $this->indexExists('user_roles', 'user_roles_user_role_company_unique')) {
                $table->unique(['user_id', 'role_id', 'company_id'], 'user_roles_user_role_company_unique');
            }
        });
    }

    public function down(): void
    {
        Schema::table('user_roles', function (Blueprint $table) {
            if ($this->indexExists('user_roles', 'user_roles_user_role_company_unique')) {
                $table->dropUnique('user_roles_user_role_company_unique');
            }
            if (Schema::hasColumn('user_roles', 'company_id')) {
                $table->dropForeign(['company_id']);
                $table->dropColumn('company_id');
            }
            if (! $this->indexExists('user_roles', 'user_roles_user_id_role_id_unique')) {
                $table->unique(['user_id', 'role_id']);
            }
        });

        Schema::table('roles', function (Blueprint $table) {
            if ($this->indexExists('roles', 'roles_name_company_unique')) {
                $table->dropUnique('roles_name_company_unique');
            }
            if (Schema::hasColumn('roles', 'company_id')) {
                $table->dropForeign(['company_id']);
                $table->dropColumn('company_id');
            }
            if (! $this->indexExists('roles', 'roles_name_unique')) {
                $table->unique('name');
            }
        });
    }

    private function indexExists(string $table, string $indexName): bool
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return false;
        }

        $db = DB::getDatabaseName();

        return DB::table('information_schema.STATISTICS')
            ->where('TABLE_SCHEMA', $db)
            ->where('TABLE_NAME', $table)
            ->where('INDEX_NAME', $indexName)
            ->exists();
    }
};
