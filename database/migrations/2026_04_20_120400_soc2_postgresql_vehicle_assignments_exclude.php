<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
CREATE EXTENSION IF NOT EXISTS btree_gist;

ALTER TABLE vehicle_assignments
  ADD COLUMN IF NOT EXISTS assign_range daterange
  GENERATED ALWAYS AS (daterange(from_date, COALESCE(to_date, '9999-12-31'::date), '[]')) STORED;

ALTER TABLE vehicle_assignments DROP CONSTRAINT IF EXISTS vehicle_assignments_no_driver_overlap;
ALTER TABLE vehicle_assignments
  ADD CONSTRAINT vehicle_assignments_no_driver_overlap
  EXCLUDE USING gist (driver_id WITH =, assign_range WITH &&);

ALTER TABLE vehicle_assignments DROP CONSTRAINT IF EXISTS vehicle_assignments_no_vehicle_overlap;
ALTER TABLE vehicle_assignments
  ADD CONSTRAINT vehicle_assignments_no_vehicle_overlap
  EXCLUDE USING gist (vehicle_id WITH =, assign_range WITH &&);
SQL);
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
ALTER TABLE vehicle_assignments DROP CONSTRAINT IF EXISTS vehicle_assignments_no_driver_overlap;
ALTER TABLE vehicle_assignments DROP CONSTRAINT IF EXISTS vehicle_assignments_no_vehicle_overlap;
ALTER TABLE vehicle_assignments DROP COLUMN IF EXISTS assign_range;
SQL);
    }
};
