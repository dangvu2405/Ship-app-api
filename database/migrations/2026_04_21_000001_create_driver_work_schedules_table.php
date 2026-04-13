<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('driver_work_schedules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('driver_id')->constrained('drivers')->cascadeOnDelete();
            $table->foreignId('office_id')->constrained('offices')->restrictOnDelete();
            $table->date('work_date');
            $table->string('shift_code', 20)->default('day');
            $table->time('start_time');
            $table->time('end_time');
            $table->foreignId('vehicle_id')->nullable()->constrained('vehicles')->nullOnDelete();
            $table->enum('status', ['draft', 'submitted', 'approved', 'locked'])->default('draft');
            $table->text('notes')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('locked_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['driver_id', 'work_date', 'shift_code'], 'dws_driver_date_shift_unique');
            $table->index(['vehicle_id', 'work_date'], 'dws_vehicle_date_idx');
            $table->index(['office_id', 'work_date', 'status'], 'dws_office_date_status_idx');
            $table->index('work_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_work_schedules');
    }
};
