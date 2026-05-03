<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table): void {
            if (! Schema::hasColumn('vehicles', 'image_front')) {
                $table->string('image_front', 255)->nullable()->after('status');
            }

            if (! Schema::hasColumn('vehicles', 'image_back')) {
                $table->string('image_back', 255)->nullable()->after('image_front');
            }

            if (! Schema::hasColumn('vehicles', 'image_side')) {
                $table->string('image_side', 255)->nullable()->after('image_back');
            }

            if (! Schema::hasColumn('vehicles', 'image_other')) {
                $table->string('image_other', 255)->nullable()->after('image_side');
            }
        });

        Schema::table('drivers', function (Blueprint $table): void {
            foreach (['vehicle_image_side', 'vehicle_image_back', 'vehicle_image_front', 'vehicle_image'] as $column) {
                if (Schema::hasColumn('drivers', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table): void {
            foreach (['image_other', 'image_side', 'image_back', 'image_front'] as $column) {
                if (Schema::hasColumn('vehicles', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('drivers', function (Blueprint $table): void {
            $anchor = Schema::hasColumn('drivers', 'avatar_url') ? 'avatar_url' : (Schema::hasColumn('drivers', 'address') ? 'address' : null);

            foreach ([
                'vehicle_image' => 'string',
                'vehicle_image_front' => 'string',
                'vehicle_image_back' => 'string',
                'vehicle_image_side' => 'string',
            ] as $column => $_type) {
                if (! Schema::hasColumn('drivers', $column)) {
                    if ($anchor !== null && $column === 'vehicle_image') {
                        $table->string($column, 255)->nullable()->after($anchor);
                    } else {
                        $table->string($column, 255)->nullable();
                    }
                }
            }
        });
    }
};
