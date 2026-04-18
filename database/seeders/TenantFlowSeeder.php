<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Creates 3 test accounts that exercise the 3 tenant-selection flows:
 *
 *  multi@tenant.test   → 2 companies → /select-tenant card picker
 *  single@tenant.test  → 1 company   → auto-select → /dashboard
 *  legacy@tenant.test  → 0 rows      → /dashboard   (backward compat)
 */
class TenantFlowSeeder extends Seeder
{
    public function run(): void
    {
        $staffRole = Role::firstOrCreate(['name' => 'staff'], ['description' => 'Staff']);

        // Reuse the two companies already created by DatabaseSeeder
        $company1 = Company::where('code', 'COMP001')->first();
        $company2 = Company::where('code', 'COMP002')->first();

        if ($company1 === null || $company2 === null) {
            $this->command->warn('TenantFlowSeeder: COMP001/COMP002 not found. Run DatabaseSeeder first.');
            return;
        }

        // ── 1. Multi-tenant user (shows picker) ─────────────────────────────
        $multi = User::firstOrCreate(
            ['email' => 'multi@tenant.test'],
            [
                'username' => 'multi_tenant',
                'password' => Hash::make('password'),
                'status'   => 'active',
            ]
        );
        $multi->roles()->syncWithoutDetaching([$staffRole->id]);
        // Assign both companies; COMP002 is the default
        $multi->companies()->syncWithoutDetaching([
            $company1->id => ['is_default' => false],
            $company2->id => ['is_default' => true],
        ]);

        // ── 2. Single-tenant user (auto-selects) ────────────────────────────
        $single = User::firstOrCreate(
            ['email' => 'single@tenant.test'],
            [
                'username' => 'single_tenant',
                'password' => Hash::make('password'),
                'status'   => 'active',
            ]
        );
        $single->roles()->syncWithoutDetaching([$staffRole->id]);
        $single->companies()->syncWithoutDetaching([
            $company1->id => ['is_default' => true],
        ]);

        // ── 3. Legacy user (0 rows → dashboard) ─────────────────────────────
        User::firstOrCreate(
            ['email' => 'legacy@tenant.test'],
            [
                'username' => 'legacy_tenant',
                'password' => Hash::make('password'),
                'status'   => 'active',
            ]
        )->roles()->syncWithoutDetaching([$staffRole->id]);
        // No user_companies rows → resolveTenants() returns []

        $this->command->info('TenantFlowSeeder: created multi@, single@, legacy@tenant.test (password: "password")');
    }
}
