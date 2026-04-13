<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('violations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('driver_id')->constrained('drivers')->restrictOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('trip_id')->nullable()->constrained('trips')->nullOnDelete();
            $table->string('type', 50)->comment('speeding, route_deviation, fuel_misuse, behavior, accident, other');
            $table->timestamp('occurred_at');
            $table->foreignId('reported_by')->constrained('users')->restrictOnDelete();
            $table->text('description');
            $table->decimal('penalty_amount', 15, 2)->default(0);
            $table->enum('status', ['pending', 'confirmed', 'disputed', 'waived'])->default('pending');
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignId('waived_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('waived_at')->nullable();
            $table->text('waive_reason')->nullable();
            $table->json('evidence_urls')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['driver_id', 'status'], 'viol_driver_status_idx');
            $table->index(['company_id', 'status'], 'viol_company_status_idx');
            $table->index(['trip_id'], 'viol_trip_idx');
            $table->index('occurred_at');
        });

        Schema::create('violation_disputes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('violation_id')->constrained('violations')->cascadeOnDelete();
            $table->foreignId('driver_id')->constrained('drivers')->restrictOnDelete();
            $table->text('reason');
            $table->json('evidence_urls')->nullable();
            $table->enum('status', ['open', 'under_review', 'resolved_upheld', 'resolved_overturned'])->default('open');
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution_note')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['violation_id', 'status'], 'vd_violation_status_idx');
            $table->index('driver_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('violation_disputes');
        Schema::dropIfExists('violations');
    }
};
