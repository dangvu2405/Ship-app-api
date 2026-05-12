<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_details', function (Blueprint $table) {
            $table->foreignId('created_by')->nullable()->after('meta_json')->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->after('updated_by')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('payroll_details')) {
            Schema::table('payroll_details', function (Blueprint $table) {
                if (Schema::hasColumn('payroll_details', 'created_by')) {
                    if (DB::getDriverName() !== 'sqlite') {
                        $table->dropForeign(['created_by']);
                        $table->dropForeign(['updated_by']);
                        $table->dropForeign(['deleted_by']);
                    }
                    $table->dropColumn(['created_by', 'updated_by', 'deleted_by']);
                }
            });
        }
    }
};
