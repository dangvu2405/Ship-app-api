<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table): void {
            if (! Schema::hasColumn('audit_logs', 'company_id')) {
                $table->foreignId('company_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
                $table->index('company_id');
            }

            if (! Schema::hasColumn('audit_logs', 'resource')) {
                $table->string('resource', 191)->nullable()->after('table_name');
            }

            if (! Schema::hasColumn('audit_logs', 'request_id')) {
                $table->string('request_id', 100)->nullable()->after('ip_address');
            }

            if (! Schema::hasColumn('audit_logs', 'user_agent')) {
                $table->string('user_agent', 512)->nullable()->after('request_id');
            }

            if (! Schema::hasColumn('audit_logs', 'metadata')) {
                $table->json('metadata')->nullable()->after('new_data');
            }
        });

        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->string('action', 100)->change();
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->string('action', 50)->change();
        });

        Schema::table('audit_logs', function (Blueprint $table): void {
            if (Schema::hasColumn('audit_logs', 'company_id')) {
                $table->dropForeign(['company_id']);
                $table->dropIndex(['company_id']);
                $table->dropColumn('company_id');
            }

            if (Schema::hasColumn('audit_logs', 'resource')) {
                $table->dropColumn('resource');
            }

            if (Schema::hasColumn('audit_logs', 'request_id')) {
                $table->dropColumn('request_id');
            }

            if (Schema::hasColumn('audit_logs', 'user_agent')) {
                $table->dropColumn('user_agent');
            }

            if (Schema::hasColumn('audit_logs', 'metadata')) {
                $table->dropColumn('metadata');
            }
        });
    }
};
