<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('drivers', function (Blueprint $table): void {
            if (! Schema::hasColumn('drivers', 'vehicle_image')) {
                $table->string('vehicle_image', 255)->nullable()->after('avatar_url');
            }

            if (! Schema::hasColumn('drivers', 'vehicle_image_front')) {
                $table->string('vehicle_image_front', 255)->nullable()->after('vehicle_image');
            }

            if (! Schema::hasColumn('drivers', 'vehicle_image_back')) {
                $table->string('vehicle_image_back', 255)->nullable()->after('vehicle_image_front');
            }

            if (! Schema::hasColumn('drivers', 'vehicle_image_side')) {
                $table->string('vehicle_image_side', 255)->nullable()->after('vehicle_image_back');
            }
        });
    }

    public function down(): void
    {
        Schema::table('drivers', function (Blueprint $table): void {
            foreach (['vehicle_image_side', 'vehicle_image_back', 'vehicle_image_front', 'vehicle_image'] as $column) {
                if (Schema::hasColumn('drivers', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
