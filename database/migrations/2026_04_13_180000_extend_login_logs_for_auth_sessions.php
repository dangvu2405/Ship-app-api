<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('login_logs', function (Blueprint $table): void {
            if (! Schema::hasColumn('login_logs', 'logout_at')) {
                $table->timestamp('logout_at')->nullable()->after('login_at');
            }
            if (! Schema::hasColumn('login_logs', 'status')) {
                $table->enum('status', ['active', 'logged_out', 'expired'])->default('active')->after('logout_at');
            }
            if (! Schema::hasColumn('login_logs', 'action')) {
                $table->string('action', 50)->default('login')->after('status');
            }
            if (! Schema::hasColumn('login_logs', 'performed_by')) {
                $table->string('performed_by', 255)->nullable()->after('action');
            }
        });
    }

    public function down(): void
    {
        Schema::table('login_logs', function (Blueprint $table): void {
            foreach (['performed_by', 'action', 'status', 'logout_at'] as $column) {
                if (Schema::hasColumn('login_logs', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};

