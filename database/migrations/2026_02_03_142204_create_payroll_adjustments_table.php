<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('payroll_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_detail_id')->constrained()->onDelete('cascade');
            $table->enum('type', ['addition', 'deduction'])->default('addition');
            $table->text('reason');
            $table->decimal('amount', 15, 2);
            $table->timestamps();
            $table->softDeletes();

            $table->index('payroll_detail_id');
            $table->index('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payroll_adjustments');
    }
};
