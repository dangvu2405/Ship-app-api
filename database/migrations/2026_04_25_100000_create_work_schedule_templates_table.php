<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Khung giờ / mẫu lịch (Schedule template) — dùng khi áp dụng hàng loạt cho văn phòng.
 * Bảng thực tế: work_schedule_templates.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_schedule_templates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('shift_code', 20)->default('day');
            $table->time('start_time');
            $table->time('end_time');
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_schedule_templates');
    }
};
