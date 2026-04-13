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
        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement("ALTER TABLE payrolls MODIFY COLUMN status ENUM('draft','approved','locked') NOT NULL DEFAULT 'draft'");
        } else {
            // SQLite does not support ALTER COLUMN enum changes; use string type
            Schema::table('payrolls', function (Blueprint $table): void {
                $table->string('status', 20)->default('draft')->change();
            });
        }

        Schema::table('payrolls', function (Blueprint $table): void {
            if (! Schema::hasColumn('payrolls', 'approved_by')) {
                $table->foreignId('approved_by')->nullable()->after('locked_at')
                    ->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('payrolls', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('approved_by');
            }
        });
    }

    public function down(): void
    {
        Schema::table('payrolls', function (Blueprint $table): void {
            if (Schema::hasColumn('payrolls', 'approved_at')) {
                $table->dropColumn('approved_at');
            }
            if (Schema::hasColumn('payrolls', 'approved_by')) {
                $table->dropConstrainedForeignId('approved_by');
            }
        });

        $driver = DB::connection()->getDriverName();
        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement("ALTER TABLE payrolls MODIFY COLUMN status ENUM('draft','locked') NOT NULL DEFAULT 'draft'");
        }
    }
};
