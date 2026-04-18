<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Restructures payroll_adjustments to support retroactive adjustments:
 * e.g. violation penalty refunded in current month for a locked prior month.
 *
 * The old table was linked to payroll_details (dropped in 2026_04_21_000011).
 * This migration drops the stale schema and creates a clean replacement.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('payroll_adjustments');

        Schema::create('payroll_adjustments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies');

            // The payroll period where this adjustment is applied (usually current month).
            $table->foreignId('payroll_id')->constrained('payrolls');

            // The original payroll period where the underlying event occurred (nullable for manual).
            $table->foreignId('original_payroll_id')->nullable()->constrained('payrolls');

            $table->foreignId('driver_id')->constrained('drivers');

            // addition = money owed to driver | deduction = money owed by driver
            $table->enum('type', ['addition', 'deduction']);

            // Specific reason category for reporting
            $table->enum('category', ['violation_refund', 'leave_restore', 'ot_late_approval', 'manual'])
                ->default('manual');

            $table->decimal('amount', 15, 2);
            $table->text('reason');

            // Polymorphic reference to the source record (e.g. Violation id=42)
            $table->string('source_type', 50)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();

            $table->foreignId('approved_by')->nullable()->constrained('users');

            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'driver_id']);
            $table->index(['payroll_id', 'driver_id']);
            $table->index(['original_payroll_id']);
            $table->index(['source_type', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_adjustments');

        // Restore legacy schema (empty stub — data was already lost)
        Schema::create('payroll_adjustments', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('payroll_detail_id');
            $table->enum('type', ['addition', 'deduction'])->default('addition');
            $table->text('reason');
            $table->decimal('amount', 15, 2);
            $table->timestamps();
            $table->softDeletes();
            $table->index('payroll_detail_id');
        });
    }
};
