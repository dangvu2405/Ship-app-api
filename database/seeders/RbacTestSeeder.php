<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Department;
use App\Models\Driver;
use App\Models\Office;
use App\Models\Permission;
use App\Models\Position;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seed dữ liệu mẫu để test hệ thống RBAC 3 cấp:
 *
 *   admin           → truy cập mọi công ty (global role)
 *   company_admin   → truy cập toàn bộ dữ liệu của 1 công ty
 *   office_admin    → chỉ truy cập dữ liệu của 1 văn phòng trong công ty
 *
 * Tài khoản test (password đều là: password):
 * ┌────────────────────┬──────────────────┬───────────────────────────────────────┐
 * │ Username           │ Role             │ Phạm vi truy cập                      │
 * ├────────────────────┼──────────────────┼───────────────────────────────────────┤
 * │ superadmin         │ admin            │ Tất cả công ty                        │
 * │ ca_abc             │ company_admin    │ Toàn bộ ABC Transport (2 văn phòng)   │
 * │ oa_hcm             │ office_admin     │ Chỉ VP Hồ Chí Minh (ABC Transport)   │
 * │ oa_hn              │ office_admin     │ Chỉ VP Hà Nội (ABC Transport)        │
 * │ ca_xyz             │ company_admin    │ Toàn bộ XYZ Logistics                 │
 * └────────────────────┴──────────────────┴───────────────────────────────────────┘
 */
class RbacTestSeeder extends Seeder
{
    public function run(): void
    {
        // ── 1. Permissions ───────────────────────────────────────────────────────
        $permCodes = [
            'drivers'            => 'Quản lý tài xế',
            'vehicles'           => 'Quản lý phương tiện',
            'trips'              => 'Quản lý chuyến đi',
            'invoices'           => 'Quản lý hoá đơn',
            'payrolls'           => 'Quản lý bảng lương',
            'schedule.approve'   => 'Duyệt lịch làm việc',
            'offices'            => 'Quản lý văn phòng',
            'departments'        => 'Quản lý phòng ban',
            'users'              => 'Quản lý người dùng',
            'reports'            => 'Xem báo cáo',
        ];

        $permissions = [];
        foreach ($permCodes as $code => $name) {
            $permissions[$code] = Permission::firstOrCreate(
                ['code' => $code],
                ['name' => $name, 'description' => $name]
            );
        }

        // ── 2. Companies & Offices ───────────────────────────────────────────────
        $abc = Company::firstOrCreate(
            ['code' => 'ABC'],
            [
                'name'     => 'ABC Transport',
                'tax_code' => '0123456789',
                'address'  => '100 Lê Lợi, TP.HCM',
                'phone'    => '0281234567',
                'email'    => 'info@abctransport.vn',
                'status'   => 'active',
            ]
        );

        $xyz = Company::firstOrCreate(
            ['code' => 'XYZ'],
            [
                'name'     => 'XYZ Logistics',
                'tax_code' => '9876543210',
                'address'  => '200 Trần Hưng Đạo, HN',
                'phone'    => '0249876543',
                'email'    => 'info@xyzlogistics.vn',
                'status'   => 'active',
            ]
        );

        $officeHCM = Office::firstOrCreate(
            ['code' => 'ABC-HCM'],
            [
                'company_id' => $abc->id,
                'name'       => 'VP Hồ Chí Minh',
                'address'    => '100 Lê Lợi, Quận 1, TP.HCM',
            ]
        );

        $officeHN = Office::firstOrCreate(
            ['code' => 'ABC-HN'],
            [
                'company_id' => $abc->id,
                'name'       => 'VP Hà Nội',
                'address'    => '50 Đinh Tiên Hoàng, Hoàn Kiếm, HN',
            ]
        );

        $officeXYZ = Office::firstOrCreate(
            ['code' => 'XYZ-HN'],
            [
                'company_id' => $xyz->id,
                'name'       => 'XYZ Hà Nội',
                'address'    => '200 Trần Hưng Đạo, HN',
            ]
        );

        // ── 3. Departments & Positions ───────────────────────────────────────────
        $deptHCM = Department::firstOrCreate(
            ['code' => 'FLEET-HCM'],
            ['office_id' => $officeHCM->id, 'name' => 'Đội xe HCM']
        );
        $deptHN = Department::firstOrCreate(
            ['code' => 'FLEET-HN'],
            ['office_id' => $officeHN->id, 'name' => 'Đội xe Hà Nội']
        );

        $posDriver = Position::firstOrCreate(
            ['code' => 'DRV'],
            ['company_id' => $abc->id, 'name' => 'Tài xế', 'base_salary' => 8000000, 'level' => 2]
        );

        // ── 4. Sample Drivers ────────────────────────────────────────────────────
        $this->createDriver('DRV-HCM-01', 'Nguyễn Văn An', $abc, $officeHCM, $deptHCM, $posDriver);
        $this->createDriver('DRV-HCM-02', 'Trần Thị Bình', $abc, $officeHCM, $deptHCM, $posDriver);
        $this->createDriver('DRV-HN-01',  'Lê Văn Cường', $abc, $officeHN, $deptHN, $posDriver);
        $this->createDriver('DRV-HN-02',  'Phạm Thị Dung', $abc, $officeHN, $deptHN, $posDriver);

        // ── 5. Roles (company_id = null → global) ────────────────────────────────
        $roleAdmin = Role::firstOrCreate(
            ['name' => 'admin', 'company_id' => null],
            ['description' => 'Quản trị hệ thống toàn cục']
        );

        $roleCA_ABC = Role::firstOrCreate(
            ['name' => 'company_admin', 'company_id' => $abc->id],
            ['description' => 'Quản trị toàn bộ ABC Transport']
        );

        $roleOA_ABC = Role::firstOrCreate(
            ['name' => 'office_admin', 'company_id' => $abc->id],
            ['description' => 'Quản trị văn phòng (ABC Transport)']
        );

        $roleCA_XYZ = Role::firstOrCreate(
            ['name' => 'company_admin', 'company_id' => $xyz->id],
            ['description' => 'Quản trị toàn bộ XYZ Logistics']
        );

        // Phân quyền
        $allPermIds = collect($permissions)->pluck('id')->all();

        $roleAdmin->permissions()->sync($allPermIds);

        $roleCA_ABC->permissions()->sync($allPermIds);
        $roleCA_XYZ->permissions()->sync($allPermIds);

        $roleOA_ABC->permissions()->sync([
            $permissions['drivers']->id,
            $permissions['vehicles']->id,
            $permissions['trips']->id,
            $permissions['schedule.approve']->id,
            $permissions['departments']->id,
            $permissions['reports']->id,
        ]);

        // ── 6. Users ─────────────────────────────────────────────────────────────
        $superadmin = $this->createUser('superadmin', 'superadmin@ship.test');
        $caABC      = $this->createUser('ca_abc',     'ca_abc@ship.test');
        $oaHCM      = $this->createUser('oa_hcm',     'oa_hcm@ship.test');
        $oaHN       = $this->createUser('oa_hn',      'oa_hn@ship.test');
        $caXYZ      = $this->createUser('ca_xyz',     'ca_xyz@ship.test');

        // Gán vào user_companies
        foreach ([$superadmin] as $u) {
            $u->companies()->syncWithoutDetaching([$abc->id => ['is_default' => true]]);
            $u->companies()->syncWithoutDetaching([$xyz->id => ['is_default' => false]]);
        }
        $caABC->companies()->syncWithoutDetaching([$abc->id => ['is_default' => true]]);
        $oaHCM->companies()->syncWithoutDetaching([$abc->id => ['is_default' => true]]);
        $oaHN->companies()->syncWithoutDetaching([$abc->id  => ['is_default' => true]]);
        $caXYZ->companies()->syncWithoutDetaching([$xyz->id => ['is_default' => true]]);

        // ── 7. Gán Roles vào user_roles ──────────────────────────────────────────

        // superadmin: global admin (company_id = null, office_id = null)
        $this->attachRole($superadmin, $roleAdmin, null, null);

        // ca_abc: company_admin của ABC (company_id = $abc->id, office_id = null)
        $this->attachRole($caABC, $roleCA_ABC, $abc->id, null);

        // oa_hcm: office_admin của VP HCM trong ABC
        $this->attachRole($oaHCM, $roleOA_ABC, $abc->id, $officeHCM->id);

        // oa_hn: office_admin của VP HN trong ABC
        $this->attachRole($oaHN, $roleOA_ABC, $abc->id, $officeHN->id);

        // ca_xyz: company_admin của XYZ
        $this->attachRole($caXYZ, $roleCA_XYZ, $xyz->id, null);

        // ── 8. Summary ───────────────────────────────────────────────────────────
        $this->command->info('');
        $this->command->info('✅  RBAC Test Data seeded');
        $this->command->info('');
        $this->command->table(
            ['Username', 'Password', 'Role', 'Phạm vi'],
            [
                ['superadmin', 'password', 'admin',        'Tất cả công ty'],
                ['ca_abc',     'password', 'company_admin','ABC Transport — toàn bộ'],
                ['oa_hcm',     'password', 'office_admin', 'ABC Transport — VP HCM only'],
                ['oa_hn',      'password', 'office_admin', 'ABC Transport — VP HN only'],
                ['ca_xyz',     'password', 'company_admin','XYZ Logistics — toàn bộ'],
            ]
        );
        $this->command->info('');
        $this->command->info('Header khi gọi API: X-Tenant-ID: ' . $abc->id . '  (ABC)  |  ' . $xyz->id . '  (XYZ)');
        $this->command->info('Office HCM id=' . $officeHCM->id . '  |  Office HN id=' . $officeHN->id);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────────

    private function createUser(string $username, string $email): User
    {
        return User::firstOrCreate(
            ['username' => $username],
            [
                'email'    => $email,
                'password' => Hash::make('password'),
                'status'   => 'active',
            ]
        );
    }

    private function createDriver(
        string $code,
        string $name,
        Company $company,
        Office $office,
        Department $dept,
        Position $position,
    ): Driver {
        return Driver::firstOrCreate(
            ['code' => $code],
            [
                'name'             => $name,
                'email'            => strtolower(str_replace([' ', '-'], '.', $code)) . '@ship.test',
                'phone'            => '09' . rand(10000000, 99999999),
                'dob'              => '1990-01-01',
                'gender'           => 'male',
                'address'          => $office->address,
                'company_id'       => $company->id,
                'office_id'        => $office->id,
                'department_id'    => $dept->id,
                'position_id'      => $position->id,
                'status'           => 'active',
                'join_date'        => '2023-01-01',
                'license_no'       => 'LIC-' . $code,
                'license_class'    => 'B2',
                'expired_date'     => '2030-12-31',
                'available_status' => 'available',
            ]
        );
    }

    private function attachRole(User $user, Role $role, ?int $companyId, ?int $officeId): void
    {
        // Tránh duplicate — detach trước nếu đã tồn tại cùng combo
        $user->roles()
            ->wherePivot('role_id', $role->id)
            ->wherePivot('company_id', $companyId)
            ->wherePivot('office_id', $officeId)
            ->detach();

        $user->roles()->attach($role->id, [
            'company_id' => $companyId,
            'office_id'  => $officeId,
        ]);
    }
}
