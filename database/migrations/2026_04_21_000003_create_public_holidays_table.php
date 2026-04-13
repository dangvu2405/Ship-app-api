<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('public_holidays', function (Blueprint $table): void {
            $table->id();
            $table->string('country_code', 5)->default('VN');
            $table->unsignedSmallInteger('year');
            $table->date('date');
            $table->string('name');
            $table->enum('holiday_type', ['national', 'regional', 'compensatory'])->default('national');
            $table->boolean('is_compensatory')->default(false);
            $table->date('compensatory_for')->nullable()->comment('Original holiday date if this is a compensatory day off');
            $table->timestamps();

            $table->unique(['country_code', 'date'], 'ph_country_date_unique');
            $table->index(['country_code', 'year'], 'ph_country_year_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('public_holidays');
    }
};
