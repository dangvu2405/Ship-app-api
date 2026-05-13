<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminRoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Dự án Ship ERP dùng cột string `role` trong bảng `users`
        User::updateOrCreate(
            ['email' => 'admin@abctransport.com'],
            [
                'name' => 'System Super Admin',
                'username' => 'superadmin',
                'password' => Hash::make('password'),
                'role' => 'super_admin',
                'status' => 'active',
            ]
        );
    }
}
