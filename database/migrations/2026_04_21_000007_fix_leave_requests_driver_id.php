<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The merge_employees_into_drivers migration dropped leave_types/leave_requests/leave_balances.
 * This migration recreates them with driver_id as the primary FK.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('leave_types')) {
            Schema::create('leave_types', function (Blueprint $table): void {
                $table->id();
                $table->string('code', 50)->unique();
                $table->string('name');
                $table->boolean('is_paid')->default(true);
                $table->unsignedSmallInteger('annual_quota_days')->default(0);
                $table->boolean('allow_carry_forward')->default(false);
                $table->boolean('requires_attachment')->default(false);
                $table->enum('status', ['active', 'inactive'])->default('active');
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('leave_requests')) {
            Schema::create('leave_requests', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('driver_id')->constrained('drivers')->cascadeOnDelete();
                $table->foreignId('leave_type_id')->constrained('leave_types')->restrictOnDelete();
                $table->date('from_date');
                $table->date('to_date');
                $table->decimal('total_days', 5, 1);
                $table->text('reason')->nullable();
                $table->enum('status', ['pending', 'approved', 'rejected', 'cancelled'])->default('pending');
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('approved_at')->nullable();
                $table->text('rejection_reason')->nullable();
                $table->json('attachment_urls')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['driver_id', 'status'], 'lr_driver_status_idx');
                $table->index(['from_date', 'to_date'], 'lr_date_range_idx');
            });
        }

        if (! Schema::hasTable('leave_balances')) {
            Schema::create('leave_balances', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('driver_id')->constrained('drivers')->cascadeOnDelete();
                $table->foreignId('leave_type_id')->constrained('leave_types')->restrictOnDelete();
                $table->unsignedSmallInteger('year');
                $table->decimal('entitled_days', 5, 1)->default(0);
                $table->decimal('used_days', 5, 1)->default(0);
                $table->decimal('carried_forward_days', 5, 1)->default(0);
                $table->timestamps();

                $table->unique(['driver_id', 'leave_type_id', 'year'], 'lb_driver_type_year_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_balances');
        Schema::dropIfExists('leave_requests');
        Schema::dropIfExists('leave_types');
    }
};
