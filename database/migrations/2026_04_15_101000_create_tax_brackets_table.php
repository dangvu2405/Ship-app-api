<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_brackets', function (Blueprint $table): void {
            $table->id();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->integer('level');
            $table->decimal('income_from', 15, 2)->default(0);
            $table->decimal('income_to', 15, 2)->nullable();
            $table->decimal('tax_rate', 5, 2);
            $table->decimal('quick_deduction', 15, 2)->default(0);
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->index(['effective_from', 'effective_to']);
            $table->unique(['effective_from', 'level']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_brackets');
    }
};
