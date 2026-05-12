<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            $table->foreignId('payroll_period_id')->nullable()->after('company_id')->constrained('payroll_periods')->nullOnDelete();
            $table->timestamp('calculated_at')->nullable()->after('locked_at');
            $table->foreignId('calculated_by')->nullable()->after('calculated_at')->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('calculated_by');
            $table->foreignId('approved_by')->nullable()->after('approved_at')->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at')->nullable()->after('approved_by');
            $table->text('notes')->nullable()->after('paid_at');
            $table->foreignId('created_by')->nullable()->after('notes')->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->after('updated_by')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('payrolls')) {
            Schema::table('payrolls', function (Blueprint $table) {
                if (Schema::hasColumn('payrolls', 'payroll_period_id')) {
                    if (DB::getDriverName() !== 'sqlite') {
                        $table->dropForeign(['payroll_period_id']);
                        $table->dropForeign(['calculated_by']);
                        $table->dropForeign(['approved_by']);
                        $table->dropForeign(['created_by']);
                        $table->dropForeign(['updated_by']);
                        $table->dropForeign(['deleted_by']);
                    }
                    $table->dropColumn([
                        'payroll_period_id',
                        'calculated_at',
                        'calculated_by',
                        'approved_at',
                        'approved_by',
                        'paid_at',
                        'notes',
                        'created_by',
                        'updated_by',
                        'deleted_by',
                    ]);
                }
            });
        }
    }
};
