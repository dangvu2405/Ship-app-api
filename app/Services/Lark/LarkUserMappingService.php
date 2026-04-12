<?php

declare(strict_types=1);

namespace App\Services\Lark;

use App\Models\User;

class LarkUserMappingService
{
    public function findByLarkUserId(?string $larkUserId): ?User
    {
        if (! $larkUserId) {
            return null;
        }

        return User::with(['employee', 'roles.permissions'])
            ->where('lark_user_id', $larkUserId)
            ->where('status', 'active')
            ->first();
    }
}
