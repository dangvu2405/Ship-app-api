<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;

class AssignSuperAdminRole extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:assign-super-admin {email}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Assign the super_admin role to a user by email';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $email = $this->argument('email');
        $user = User::where('email', $email)->first();

        if (!$user) {
            $this->error("User with email {$email} not found.");
            return 1;
        }

        $superAdminRole = Role::where('name', 'super_admin')->first();

        if (!$superAdminRole) {
            $this->error("Role 'super_admin' not found. Please run SuperAdminRoleSeeder first.");
            return 1;
        }

        $user->roles()->syncWithoutDetaching([$superAdminRole->id]);

        // Also update the legacy 'role' column for compatibility
        $user->update(['role' => 'super_admin']);

        $this->info("Successfully assigned 'super_admin' role to {$email}.");

        return 0;
    }
}
