<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payrolls', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('month');
            $table->unsignedSmallInteger('year');
            $table->enum('status', ['draft', 'locked'])->default('draft');
            $table->timestamp('locked_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'month', 'year']);
            $table->index(['company_id', 'status']);
            $table->index(['year', 'month']);
        });

        Schema::create('payroll_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payroll_id')->constrained()->cascadeOnDelete();
            $table->foreignId('driver_id')->constrained('drivers')->restrictOnDelete();
            $table->decimal('base_salary', 15, 2)->default(0);
            $table->decimal('trip_bonus', 15, 2)->default(0);
            $table->decimal('allowance', 15, 2)->default(0);
            $table->decimal('deduction', 15, 2)->default(0);
            $table->decimal('fuel_cost', 15, 2)->default(0);
            $table->decimal('tax', 15, 2)->default(0);
            $table->decimal('net_salary', 15, 2)->default(0);
            $table->unsignedSmallInteger('working_days')->default(22);
            $table->unsignedInteger('trips_completed_count')->default(0);
            $table->decimal('total_distance_km', 12, 2)->default(0);
            $table->json('meta_json')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['payroll_id', 'driver_id']);
            $table->index('driver_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_lines');
        Schema::dropIfExists('payrolls');
    }
};
