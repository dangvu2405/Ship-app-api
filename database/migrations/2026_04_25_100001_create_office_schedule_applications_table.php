<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lịch sử áp dụng template cho văn phòng (audit + tham chiếu kỳ áp dụng).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('office_schedule_applications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('office_id')->constrained()->cascadeOnDelete();
            $table->foreignId('work_schedule_template_id')->constrained('work_schedule_templates')->restrictOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->foreignId('applied_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('drivers_affected')->default(0);
            $table->unsignedInteger('rows_created')->default(0);
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['office_id', 'start_date', 'end_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('office_schedule_applications');
    }
};
