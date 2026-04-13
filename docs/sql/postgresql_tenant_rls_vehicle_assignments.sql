-- PostgreSQL sample: tenant isolation (RLS) + overlap protection for vehicle_assignments
-- Prerequisite: column company_id BIGINT NOT NULL on tenant tables, indexed (company_id, ...).
--
-- Session GUC (per-transaction recommended with PgBouncer transaction pooling):
--   SELECT set_config('app.current_company_id', '<bigint-as-text>', true);
-- Laravel: DB::statement("SELECT set_config('app.current_company_id', ?, true)", [(string) $companyId]);
--
-- NOTE: policies use BIGINT, not UUID.

CREATE EXTENSION IF NOT EXISTS btree_gist;

ALTER TABLE vehicle_assignments ENABLE ROW LEVEL SECURITY;
ALTER TABLE vehicle_assignments FORCE ROW LEVEL SECURITY;

DROP POLICY IF EXISTS tenant_isolation_vehicle_assignments ON vehicle_assignments;

CREATE POLICY tenant_isolation_vehicle_assignments
ON vehicle_assignments
FOR ALL
USING (company_id = current_setting('app.current_company_id', true)::bigint)
WITH CHECK (company_id = current_setting('app.current_company_id', true)::bigint);

-- Optional: overlap constraints (defense in depth; app also validates overlaps on MySQL).
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
