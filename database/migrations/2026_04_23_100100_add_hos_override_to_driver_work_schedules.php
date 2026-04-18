<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('driver_work_schedules', function (Blueprint $table): void {
            $table->text('hos_override_reason')->nullable()->after('notes')
                ->comment('Reason provided when approving a schedule that violates HOS rules (NĐ 10/2020)');
        });
    }

    public function down(): void
    {
        Schema::table('driver_work_schedules', function (Blueprint $table): void {
            $table->dropColumn('hos_override_reason');
        });
    }
};
