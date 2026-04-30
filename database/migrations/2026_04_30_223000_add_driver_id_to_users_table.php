<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'driver_id')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->foreignId('driver_id')->nullable()->after('status')->constrained('drivers')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'driver_id')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropForeign(['driver_id']);
                $table->dropColumn('driver_id');
            });
        }
    }
};
