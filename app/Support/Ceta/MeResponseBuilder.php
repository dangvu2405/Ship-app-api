<?php

declare(strict_types=1);

namespace App\Support\Ceta;

use App\Models\Company;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Schema;

/**
 * Builds GET /auth/me payload aligned with CETA spec (user, company, permissions matrix).
 */
final class MeResponseBuilder
{
    /**
     * @return array{user: array<string, mixed>, company: array<string, mixed>|null, permissions: array<string, array<string, bool>>, tenants: array<int, array<string, mixed>>}
     */
    public static function payload(User $user, TenantContext $tenant): array
    {
        $user->loadMissing(['permissions']);

        $companyId = $tenant->getCompanyId();
        $company = ($companyId !== null && $companyId > 0)
            ? Company::query()->find($companyId)
            : null;

        $userArr = $user->toArray();
        $userArr['full_name'] = $userArr['full_name'] ?? $user->username;
        unset($userArr['password'], $userArr['remember_token']);

        return [
            'user' => $userArr,
            'company' => $company !== null ? [
                'id' => $company->id,
                'name' => $company->name,
                'code' => $company->code ?? null,
            ] : null,
            'permissions' => self::permissionsMatrix($user, $companyId),
            'tenants' => $user->resolveTenants(),
        ];
    }

    /**
     * @return array<string, array<string, bool>>
     */
    private static function permissionsMatrix(User $user, ?int $companyId): array
    {
        $blank = static fn (): array => [
            'view' => false,
            'create' => false,
            'edit' => false,
            'delete' => false,
            'approve' => false,
            'export' => false,
        ];

        $modules = ['orders', 'vehicles', 'drivers', 'accounting', 'reports', 'settings'];
        $out = [];
        foreach ($modules as $m) {
            $out[$m] = $blank();
        }

        $grantAll = static function () use ($modules): array {
            $o = [];
            $all = [
                'view' => true,
                'create' => true,
                'edit' => true,
                'delete' => true,
                'approve' => true,
                'export' => true,
            ];
            foreach ($modules as $m) {
                $o[$m] = $all;
            }

            return $o;
        };

        if (Schema::hasColumn('users', 'role') && ($user->role === 'admin' || $user->role === 'super_admin')) {
            return $grantAll();
        }

        return $out;
    }
}
