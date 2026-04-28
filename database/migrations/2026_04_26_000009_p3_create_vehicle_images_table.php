<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * P3: Chuẩn hóa ảnh xe — tách 4 cột image cứng nhắc thành bảng riêng.
 *
 * Backfill: chuyển dữ liệu từ 4 cột cũ sang vehicle_images.
 * Các cột cũ (image_front, image_back, image_side, image_other) được giữ lại
 * để không break code hiện tại — drop trong migration riêng sau khi app đã update.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_images', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->enum('image_type', ['front', 'back', 'side', 'interior', 'document', 'other'])->default('other');
            $table->string('url', 512)->notNull();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['vehicle_id', 'image_type'], 'idx_vehicle_images_vehicle_type');
        });

        // Backfill từ 4 cột cũ
        $typeMap = [
            'image_front' => 'front',
            'image_back'  => 'back',
            'image_side'  => 'side',
            'image_other' => 'other',
        ];

        foreach ($typeMap as $column => $imageType) {
            if (Schema::hasColumn('vehicles', $column)) {
                DB::table('vehicles')
                    ->whereNotNull($column)
                    ->where($column, '!=', '')
                    ->chunkById(200, function ($vehicles) use ($column, $imageType): void {
                        $rows = $vehicles->map(fn ($v) => [
                            'vehicle_id' => $v->id,
                            'image_type' => $imageType,
                            'url'        => $v->{$column},
                            'sort_order' => 0,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ])->toArray();

                        DB::table('vehicle_images')->insertOrIgnore($rows);
                    });
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_images');
    }
};
