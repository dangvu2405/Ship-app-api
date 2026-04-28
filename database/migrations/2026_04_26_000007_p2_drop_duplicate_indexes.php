<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * P2: Xóa các index trùng lặp.
 * UNIQUE key đã là index — index thường trùng tên/cột thừa.
 *
 * Các index bị xóa:
 *   - attendances: attendances_driver_id_date_index (đã có unique(driver_id,date))
 *   - drivers: drivers_code_index (đã có drivers_code_unique)
 *   - companies: companies_code_index (đã có companies_code_unique)
 *   - invoices: invoices_code_index (đã có code unique)
 *   - vehicles: vehicles_plate_number_index (đã có plate_number unique)
 */
return new class extends Migration
{
    private array $duplicates = [
        'attendances'  => ['attendances_driver_id_date_index'],
        'drivers'      => ['drivers_code_index'],
        'companies'    => ['companies_code_index'],
        'invoices'     => ['invoices_code_index'],
        'vehicles'     => ['vehicles_plate_number_index'],
    ];

    public function up(): void
    {
        foreach ($this->duplicates as $table => $indexes) {
            Schema::table($table, function (Blueprint $table) use ($indexes): void {
                foreach ($indexes as $index) {
                    try {
                        $table->dropIndex($index);
                    } catch (\Throwable) {
                        // Đã bị drop hoặc tên khác — bỏ qua
                    }
                }
            });
        }
    }

    public function down(): void
    {
        // Chỉ tái tạo nếu chưa tồn tại (UNIQUE đã cover nên không cần thiết về hiệu năng,
        // nhưng restore để down() không gây lỗi)
        $restore = [
            ['attendances', fn ($t) => $t->index(['driver_id', 'date'], 'attendances_driver_id_date_index')],
            ['drivers',     fn ($t) => $t->index('code', 'drivers_code_index')],
            ['companies',   fn ($t) => $t->index('code', 'companies_code_index')],
            ['invoices',    fn ($t) => $t->index('code', 'invoices_code_index')],
            ['vehicles',    fn ($t) => $t->index('plate_number', 'vehicles_plate_number_index')],
        ];

        foreach ($restore as [$tbl, $fn]) {
            Schema::table($tbl, function (Blueprint $table) use ($fn): void {
                try { $fn($table); } catch (\Throwable) {}
            });
        }
    }
};
