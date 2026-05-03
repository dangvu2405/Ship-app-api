<?php

declare(strict_types=1);

namespace App\Services\User;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserService
{
    public function create(array $data): User
    {
        $data['password'] = Hash::make($data['password']);
        $user = User::create($data);

        return $user->load(['roles']);
    }

    public function update(User $user, array $data): User
    {
        if (! empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $user->update($data);

        return $user->fresh(['roles']);
    }

    public function updateStatus(User $user, string $status): User
    {
        $user->update(['status' => $status]);

        return $user->fresh(['roles']);
    }

    public function resetPassword(User $user, string $plainPassword): User
    {
        $user->forceFill([
            'password' => Hash::make($plainPassword),
        ])->save();

        return $user->fresh(['roles']);
    }
}
