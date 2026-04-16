<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trip_bonus_rules', function (Blueprint $table): void {
            if (! Schema::hasColumn('trip_bonus_rules', 'company_id')) {
                $table->unsignedBigInteger('company_id')->nullable()->after('id');
            }

            if (! Schema::hasColumn('trip_bonus_rules', 'effective_from')) {
                $table->date('effective_from')->default('2000-01-01')->after('company_id');
            }

            if (! Schema::hasColumn('trip_bonus_rules', 'effective_to')) {
                $table->date('effective_to')->nullable()->after('effective_from');
            }

            $table->index(['company_id', 'effective_from'], 'idx_trip_bonus_rules_company_effective_from');
        });

        // Add FK constraints if possible (MySQL allows dropping/adding depending on existing data).
        Schema::table('trip_bonus_rules', function (Blueprint $table): void {
            if (Schema::hasColumn('trip_bonus_rules', 'company_id')) {
                $table->foreign('company_id')
                    ->references('id')
                    ->on('companies')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('trip_bonus_rules', function (Blueprint $table): void {
            if (Schema::hasColumn('trip_bonus_rules', 'company_id')) {
                $table->dropForeign(['company_id']);
            }

            foreach (['effective_to', 'effective_from'] as $column) {
                if (Schema::hasColumn('trip_bonus_rules', $column)) {
                    $table->dropColumn($column);
                }
            }

            if (Schema::hasColumn('trip_bonus_rules', 'company_id')) {
                $table->dropColumn('company_id');
            }
        });
    }
};

