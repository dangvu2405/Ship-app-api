<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('driver_work_schedules', 'company_id')) {
            return;
        }

        Schema::table('driver_work_schedules', function (Blueprint $table): void {
            $table->foreignId('company_id')->nullable()->after('driver_id')->constrained()->cascadeOnDelete();
            $table->index(['company_id', 'work_date', 'status'], 'dws_company_date_status_idx');
        });

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            DB::statement('UPDATE driver_work_schedules SET company_id = (SELECT company_id FROM drivers WHERE drivers.id = driver_work_schedules.driver_id) WHERE company_id IS NULL');
        } else {
            DB::statement('UPDATE driver_work_schedules dws INNER JOIN drivers d ON d.id = dws.driver_id SET dws.company_id = d.company_id WHERE dws.company_id IS NULL');
        }

        if ($driver === 'sqlite') {
            DB::statement('UPDATE driver_work_schedules SET company_id = (SELECT company_id FROM offices WHERE offices.id = driver_work_schedules.office_id) WHERE company_id IS NULL AND office_id IS NOT NULL');
        } else {
            DB::statement('UPDATE driver_work_schedules dws INNER JOIN offices o ON o.id = dws.office_id SET dws.company_id = o.company_id WHERE dws.company_id IS NULL AND dws.office_id IS NOT NULL');
        }

        DB::table('driver_work_schedules')->whereNull('company_id')->delete();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE driver_work_schedules MODIFY company_id BIGINT UNSIGNED NOT NULL');
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('driver_work_schedules', 'company_id')) {
            return;
        }

        Schema::table('driver_work_schedules', function (Blueprint $table): void {
            $table->dropIndex('dws_company_date_status_idx');
            $table->dropConstrainedForeignId('company_id');
        });
    }
};
