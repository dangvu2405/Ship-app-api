<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('night_shift_policies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('start_hour')->default(22)->comment('Hour 0-23, start of night shift window');
            $table->unsignedTinyInteger('end_hour')->default(6)->comment('Hour 0-23, end of night shift window');
            $table->decimal('differential_pct', 5, 2)->default(30.00)->comment('Additional % on top of base hourly rate');
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'is_active'], 'nsp_company_active_idx');
            $table->index(['company_id', 'effective_from', 'effective_to'], 'nsp_company_effective_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('night_shift_policies');
    }
};
