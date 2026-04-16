<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $has_avatar_url = Schema::hasColumn('drivers', 'avatar_url');
        $has_address = Schema::hasColumn('drivers', 'address');

        // Keep the migration resilient to out-of-order schema evolution.
        // In some environments, `avatar_url` (or even `address`) may not exist on `drivers` yet.
        $anchor_column = $has_avatar_url ? 'avatar_url' : ($has_address ? 'address' : null);

        // Cache current existence so we don't create fragile `AFTER <col>` dependencies.
        $has_vehicle_image = Schema::hasColumn('drivers', 'vehicle_image');
        $has_vehicle_image_front = Schema::hasColumn('drivers', 'vehicle_image_front');
        $has_vehicle_image_back = Schema::hasColumn('drivers', 'vehicle_image_back');

        Schema::table('drivers', function (Blueprint $table) use (
            $anchor_column,
            $has_vehicle_image,
            $has_vehicle_image_front,
            $has_vehicle_image_back
        ): void {
            if (! Schema::hasColumn('drivers', 'vehicle_image')) {
                if ($anchor_column !== null) {
                    $table->string('vehicle_image', 255)->nullable()->after($anchor_column);
                } else {
                    $table->string('vehicle_image', 255)->nullable();
                }
            }

            if (! Schema::hasColumn('drivers', 'vehicle_image_front')) {
                if ($has_vehicle_image) {
                    $table->string('vehicle_image_front', 255)->nullable()->after('vehicle_image');
                } else {
                    $table->string('vehicle_image_front', 255)->nullable();
                }
            }

            if (! Schema::hasColumn('drivers', 'vehicle_image_back')) {
                if ($has_vehicle_image_front) {
                    $table->string('vehicle_image_back', 255)->nullable()->after('vehicle_image_front');
                } else {
                    $table->string('vehicle_image_back', 255)->nullable();
                }
            }

            if (! Schema::hasColumn('drivers', 'vehicle_image_side')) {
                if ($has_vehicle_image_back) {
                    $table->string('vehicle_image_side', 255)->nullable()->after('vehicle_image_back');
                } else {
                    $table->string('vehicle_image_side', 255)->nullable();
                }
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
