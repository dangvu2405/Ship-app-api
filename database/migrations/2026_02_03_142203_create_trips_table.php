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
        Schema::create('trips', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->foreignId('customer_id')->constrained()->onDelete('restrict');
            $table->foreignId('driver_id')->constrained('employees')->onDelete('restrict');
            $table->foreignId('vehicle_id')->constrained()->onDelete('restrict');
            $table->string('start_point');
            $table->string('end_point');
            $table->decimal('distance_km', 10, 2)->default(0);
            $table->datetime('start_time')->nullable();
            $table->datetime('end_time')->nullable();
            $table->decimal('price', 15, 2)->default(0);
            $table->enum('status', ['pending', 'in_progress', 'completed', 'cancelled'])->default('pending');
            $table->timestamps();
            $table->softDeletes();

            $table->index('code');
            $table->index(['driver_id', 'status']);
            $table->index(['customer_id', 'status']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trips');
    }
};
