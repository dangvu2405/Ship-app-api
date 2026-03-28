<?php

declare(strict_types=1);

namespace App\Listeners;

use Illuminate\Auth\Events\Login;
use App\Models\LoginLog;

class LogSuccessfulLogin
{
    /**
     * Handle the event.
     */
    public function handle(Login $event): void
    {
        LoginLog::create([
            'user_id' => $event->user->id,
            'ip' => request()->ip() ?? '127.0.0.1',
            'device' => substr(request()->header('User-Agent') ?? 'Unknown', 0, 255),
            'login_at' => now(),
        ]);
    }
}
