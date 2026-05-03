<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trips', function (Blueprint $table): void {
            if (! Schema::hasColumn('trips', 'assigned_at')) {
                $table->timestamp('assigned_at')->nullable()->after('status');
            }
            if (! Schema::hasColumn('trips', 'actual_delivered_at')) {
                $table->timestamp('actual_delivered_at')->nullable()->after('end_time');
            }
        });

        // Add new enum values: assigned, delivered
        DB::statement("ALTER TABLE `trips` MODIFY COLUMN `status` ENUM('pending','assigned','in_progress','delivered','completed','cancelled') NOT NULL DEFAULT 'pending'");
    }

    public function down(): void
    {
        // Revert enum (remove assigned, delivered)
        DB::statement("ALTER TABLE `trips` MODIFY COLUMN `status` ENUM('pending','in_progress','completed','cancelled') NOT NULL DEFAULT 'pending'");

        Schema::table('trips', function (Blueprint $table): void {
            if (Schema::hasColumn('trips', 'assigned_at')) {
                $table->dropColumn('assigned_at');
            }
            if (Schema::hasColumn('trips', 'actual_delivered_at')) {
                $table->dropColumn('actual_delivered_at');
            }
        });
    }
};
