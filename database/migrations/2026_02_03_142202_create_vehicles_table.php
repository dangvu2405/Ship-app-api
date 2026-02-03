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
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('office_id')->constrained()->onDelete('cascade');
            $table->string('plate_number', 20)->unique();
            $table->enum('type', ['truck', 'van', 'car', 'motorcycle'])->default('truck');
            $table->string('brand', 100)->nullable();
            $table->string('model', 100)->nullable();
            $table->year('year')->nullable();
            $table->integer('capacity')->nullable();
            $table->enum('status', ['active', 'maintenance', 'inactive'])->default('active');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['office_id', 'status']);
            $table->index('plate_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
