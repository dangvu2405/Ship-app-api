<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

final class NotificationAlertService
{
    public function dispatchDueAlerts(int $withinDays = 7): int
    {
        return 0;
    }

    /** @return Collection<int, User> */
    private function recipientUsers(int $companyId): Collection
    {
        $query = User::query()->where('status', 'active');
        if ($companyId > 0 && Schema::hasColumn('users', 'company_id')) {
            $query->where('company_id', $companyId);
        }

        $users = $query->get();

        return $users->filter(function (User $user): bool {
            $role = strtolower((string) ($user->role ?? ''));
            if (in_array($role, ['admin', 'dispatcher'], true)) {
                return true;
            }

            if (method_exists($user, 'roles')) {
                return $user->roles()->whereIn('name', ['admin', 'dispatcher'])->exists();
            }

            return false;
        })->values();
    }

    /** @param array<string, mixed> $payload */
    private function pushNotification(User $user, array $payload): void
    {
        $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\SystemNotification',
            'data' => $payload,
        ]);
    }
}
