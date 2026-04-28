<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds office_id to user_roles so an office_admin assignment can be pinned
 * to a specific office inside a company.
 *
 * office_id IS NULL  → role applies to the whole company (company_admin, admin)
 * office_id NOT NULL → role is scoped to that office only (office_admin)
 *
 * Unique constraint: one role per (user, role, company, office) tuple.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_roles', function (Blueprint $table) {
            $table->foreignId('office_id')
                ->nullable()
                ->after('company_id')
                ->constrained('offices')
                ->nullOnDelete();

            // Drop previous unique, recreate with office_id included
            $table->dropUnique('user_roles_user_role_company_unique');
            $table->unique(
                ['user_id', 'role_id', 'company_id', 'office_id'],
                'user_roles_unique'
            );

            $table->index('office_id');
        });
    }

    public function down(): void
    {
        Schema::table('user_roles', function (Blueprint $table) {
            $table->dropUnique('user_roles_unique');
            $table->dropIndex(['office_id']);
            $table->dropForeign(['office_id']);
            $table->dropColumn('office_id');

            $table->unique(
                ['user_id', 'role_id', 'company_id'],
                'user_roles_user_role_company_unique'
            );
        });
    }
};
