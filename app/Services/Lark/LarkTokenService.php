<?php

declare(strict_types=1);

namespace App\Services\Lark;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class LarkTokenService
{
    public function getTenantAccessToken(): string
    {
        return Cache::remember('lark:tenant_access_token', now()->addMinutes(110), function (): string {
            $response = Http::timeout(10)->post(
                'https://open.larksuite.com/open-apis/auth/v3/tenant_access_token/internal',
                [
                    'app_id' => (string) config('lark.app_id'),
                    'app_secret' => (string) config('lark.app_secret'),
                ]
            );

            if (! $response->ok() || (int) $response->json('code', -1) !== 0) {
                throw new RuntimeException('Failed to fetch Lark tenant access token.');
            }

            return (string) $response->json('tenant_access_token');
        });
    }
}
