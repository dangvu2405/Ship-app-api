<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\LoginLog;
use Illuminate\Auth\Events\Login;

final class LogSuccessfulLogin
{
    public function handle(Login $event): void
    {
        LoginLog::query()->create([
            'user_id' => $event->user->getAuthIdentifier(),
            'ip' => request()->ip(),
            'device' => request()->userAgent(),
            'login_at' => now(),
            'status' => 'success',
            'action' => 'login',
            'performed_by' => null,
        ]);
    }
}
