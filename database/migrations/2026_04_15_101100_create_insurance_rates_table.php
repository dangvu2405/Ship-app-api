<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('insurance_rates', function (Blueprint $table): void {
            $table->id();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->decimal('social_employee_rate', 5, 2)->default(0);
            $table->decimal('social_company_rate', 5, 2)->default(0);
            $table->decimal('health_employee_rate', 5, 2)->default(0);
            $table->decimal('health_company_rate', 5, 2)->default(0);
            $table->decimal('unemployment_employee_rate', 5, 2)->default(0);
            $table->decimal('unemployment_company_rate', 5, 2)->default(0);
            $table->decimal('salary_cap_amount', 15, 2)->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->index(['effective_from', 'effective_to']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('insurance_rates');
    }
};
