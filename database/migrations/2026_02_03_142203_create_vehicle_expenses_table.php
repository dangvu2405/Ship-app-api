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
        Schema::create('vehicle_expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained()->onDelete('cascade');
            $table->foreignId('driver_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->enum('type', ['fuel', 'maintenance', 'repair', 'toll', 'parking', 'other'])->default('fuel');
            $table->decimal('amount', 15, 2);
            $table->text('note')->nullable();
            $table->date('expense_date');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['vehicle_id', 'expense_date']);
            $table->index('driver_id');
            $table->index('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicle_expenses');
    }
};
