<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

/**
 * Idempotent: ensures admin@abctransport.com exists with password "password" and admin access.
 */
final class EnsureAdminAbcTransportSeeder extends Seeder
{
    public function run(): void
    {
        $attributes = [
            'username' => 'admin',
            'email' => 'admin@abctransport.com',
            'password' => Hash::make('password'),
            'status' => 'active',
        ];

        if (Schema::hasColumn('users', 'role')) {
            $attributes['role'] = 'admin';
        }

        /** @var User|null $user */
        $user = User::withTrashed()
            ->where('email', 'admin@abctransport.com')
            ->orWhere('username', 'admin')
            ->first();

        if ($user === null) {
            /** @var User $user */
            $user = User::query()->create($attributes);
        } else {
            $user->fill($attributes);
            if (method_exists($user, 'restore') && $user->trashed()) {
                $user->restore();
            }
            $user->save();
        }

        $this->command?->info('Admin ready: admin@abctransport.com / password');
    }
}
