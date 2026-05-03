<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('positions', function (Blueprint $table): void {
            if (! Schema::hasColumn('positions', 'company_id')) {
                $table->unsignedBigInteger('company_id')->nullable()->after('id');
                $table->index('company_id', 'idx_positions_company_id');
            }
        });

        Schema::table('positions', function (Blueprint $table): void {
            if (Schema::hasColumn('positions', 'company_id')) {
                $table->foreign('company_id', 'fk_positions_company_id')
                    ->references('id')
                    ->on('companies')
                    ->cascadeOnDelete();
            }
        });

        Schema::table('customers', function (Blueprint $table): void {
            if (! Schema::hasColumn('customers', 'company_id')) {
                $table->unsignedBigInteger('company_id')->nullable()->after('id');
                $table->index('company_id', 'idx_customers_company_id');
            }
        });

        Schema::table('customers', function (Blueprint $table): void {
            if (Schema::hasColumn('customers', 'company_id')) {
                $table->foreign('company_id', 'fk_customers_company_id')
                    ->references('id')
                    ->on('companies')
                    ->cascadeOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            if (Schema::hasColumn('customers', 'company_id')) {
                $table->dropForeign('fk_customers_company_id');
                $table->dropIndex('idx_customers_company_id');
                $table->dropColumn('company_id');
            }
        });

        Schema::table('positions', function (Blueprint $table): void {
            if (Schema::hasColumn('positions', 'company_id')) {
                $table->dropForeign('fk_positions_company_id');
                $table->dropIndex('idx_positions_company_id');
                $table->dropColumn('company_id');
            }
        });
    }
};
