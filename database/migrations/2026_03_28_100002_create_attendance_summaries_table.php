<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_period_id')->constrained('payroll_periods')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->decimal('working_days', 5, 2)->default(0);
            $table->decimal('actual_days', 5, 2)->default(0);
            $table->decimal('leave_paid_days', 5, 2)->default(0);
            $table->decimal('leave_unpaid_days', 5, 2)->default(0);
            $table->decimal('overtime_hours', 8, 2)->default(0);
            $table->string('status', 20)->default('draft');
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source', 20)->default('computed');
            $table->json('meta_json')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['payroll_period_id', 'employee_id']);
            $table->index('employee_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_summaries');
    }
};
