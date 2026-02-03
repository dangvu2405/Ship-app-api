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
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->string('email')->unique()->nullable();
            $table->string('phone', 20)->nullable();
            $table->date('dob')->nullable();
            $table->enum('gender', ['male', 'female', 'other'])->nullable();
            $table->text('address')->nullable();
            $table->foreignId('office_id')->constrained()->onDelete('cascade');
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('position_id')->constrained()->onDelete('restrict');
            $table->enum('type', ['office', 'driver'])->default('office');
            $table->enum('status', ['active', 'inactive', 'resigned'])->default('active');
            $table->date('join_date')->nullable();
            $table->date('resign_date')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('code');
            $table->index(['office_id', 'status']);
            $table->index('type');
            $table->index('department_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
