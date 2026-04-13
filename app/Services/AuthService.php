<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Hash;

class AuthService
{
    /**
     * @return array{user: User, token: string}
     */
    public function login(string $email, string $password): array
    {
        $user = User::where('email', $email)
            ->where('status', 'active')
            ->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            throw new AuthenticationException('Invalid credentials');
        }

        $token = $user->createToken('auth-token')->plainTextToken;
        $user->update(['last_login_at' => now()]);
        $user->load(['driver', 'roles.permissions']);

        return [
            'user' => $user,
            'token' => $token,
        ];
    }

    public function register(array $payload): User
    {
        $user = User::create([
            'username' => $payload['username'],
            'email' => $payload['email'],
            'password' => Hash::make($payload['password']),
            'status' => 'active',
        ]);

        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $user->roles()->syncWithoutDetaching([$adminRole->id]);
        $user->load(['driver', 'roles.permissions']);

        return $user;
    }

    public function logout(User $user): void
    {
        $token = $user->currentAccessToken();
        if ($token) {
            $token->delete();
        }
    }

    public function refresh(User $user): string
    {
        $token = $user->currentAccessToken();
        if ($token) {
            $token->delete();
        }

        return $user->createToken('auth-token')->plainTextToken;
    }
}
