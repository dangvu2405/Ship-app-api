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
        Schema::create('drivers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id')->unique();
            $table->string('license_no', 50);
            $table->string('license_class', 20)->nullable();
            $table->date('expired_date')->nullable();
            $table->enum('available_status', ['available', 'busy', 'offline'])->default('available');
            $table->timestamps();
            $table->softDeletes();

            $table->index('license_no');
            $table->index('available_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('drivers');
    }
};
