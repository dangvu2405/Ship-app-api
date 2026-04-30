<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Company;
use App\Models\Driver;
use App\Models\User;
use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

final class EnsureTenantContext
{
    public function __construct(
        private readonly TenantContext $tenantContext
    ) {}

    /**
     * Sentinel value used when an authenticated user has no resolvable tenant.
     * BelongsToTenant will match WHERE company_id = -1 → zero rows returned.
     */
    private const NO_TENANT_SENTINEL = -1;

    public function handle(Request $request, Closure $next): Response
    {
        $requestedCompanyId = $this->requestedCompanyId($request);
        if ($requestedCompanyId !== null && ! $this->canAccessRequestedCompany($request, $requestedCompanyId)) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden: tenant mismatch',
            ], 403);
        }

        $resolved = $this->resolveCompanyId($request);

        // Authenticated but no tenant resolved → block data access with sentinel
        if ($resolved === null && $request->user() !== null) {
            $resolved = self::NO_TENANT_SENTINEL;
        }

        $this->tenantContext->setCompanyId($resolved);

        // Resolve office scope: office_admin sees only their assigned office.
        // company_admin and admin get no office restriction (null = see all offices).
        if ($resolved !== null && $resolved !== self::NO_TENANT_SENTINEL && $request->user() !== null) {
            $this->tenantContext->setOfficeId(
                $this->resolveOfficeId($request->user(), $resolved)
            );
        }

        return $next($request);
    }

    private function requestedCompanyId(Request $request): ?int
    {
        $header = $request->header('X-Tenant-ID')
            ?? $request->header('X-Company-Id');
        $query = $request->query('company_id');
        $raw = ($header !== null && $header !== '') ? $header : $query;

        if ($raw === null || $raw === '' || ! ctype_digit((string) $raw)) {
            return null;
        }

        $candidate = (int) $raw;

        return $candidate > 0 ? $candidate : null;
    }

    private function canAccessRequestedCompany(Request $request, int $companyId): bool
    {
        $user = $request->user();
        if ($user === null) {
            return false;
        }

        if ($user->hasRole('admin') || $user->hasRole('super_admin')) {
            return Company::query()->whereKey($companyId)->exists();
        }

        if (Schema::hasTable('user_companies')) {
            return $user->companies()
                ->wherePivot('company_id', $companyId)
                ->exists();
        }

        return false;
    }

    public function terminate(Request $request, Response $response): void
    {
        $this->tenantContext->reset();
    }

    private function resolveCompanyId(Request $request): ?int
    {
        $user = $request->user();

        if ($user === null) {
            return null;
        }

        // Accept X-Tenant-ID (frontend) or X-Company-Id (legacy) or query param
        $header = $request->header('X-Tenant-ID')
            ?? $request->header('X-Company-Id');
        $query = $request->query('company_id');
        $raw = ($header !== null && $header !== '') ? $header : $query;

        if ($raw !== null && $raw !== '' && ctype_digit((string) $raw)) {
            $candidate = (int) $raw;

            if ($candidate > 0) {
                if ($user->hasRole('admin') || $user->hasRole('super_admin')) {
                    // Global admin/super_admin can access any existing company
                    if (Company::query()->whereKey($candidate)->exists()) {
                        return $candidate;
                    }
                } else {
                    // company_admin / office_admin must have explicit user_companies row
                    if (Schema::hasTable('user_companies')) {
                        $assigned = $user->companies()
                            ->wherePivot('company_id', $candidate)
                            ->exists();
                        if ($assigned) {
                            return $candidate;
                        }
                    }
                }
            }
        }

        // Fall back: first assigned company (highest is_default first)
        if (Schema::hasTable('user_companies')) {
            $defaultCompany = $user->companies()->first();
            if ($defaultCompany !== null) {
                return $defaultCompany->id;
            }
        }

        $driverId = $user->getAttribute('driver_id');
        if (is_int($driverId) || ctype_digit((string) $driverId)) {
            $companyId = Driver::query()
                ->whereKey((int) $driverId)
                ->value('company_id');

            return $companyId !== null ? (int) $companyId : null;
        }

        return null;
    }

    /**
     * Returns the office_id this user is restricted to within $companyId,
     * or null if the user can see all offices (admin / company_admin).
     *
     * Logic:
     *  - admin or company_admin (with null office_id in user_roles) → null (no restriction)
     *  - office_admin → the office_id from their user_roles row for this company
     */
    private function resolveOfficeId(User $user, int $companyId): ?int
    {
        // Global admin and company_admin are never restricted to a single office
        if ($user->hasRole('admin') || $user->hasRole('company_admin', $companyId)) {
            return null;
        }

        // Find the office_admin assignment for this company
        if (! Schema::hasTable('roles') || ! Schema::hasTable('user_roles')) {
            return null;
        }

        $pivot = $user->roles()
            ->where('roles.name', 'office_admin')
            ->where('user_roles.company_id', $companyId)
            ->whereNotNull('user_roles.office_id')
            ->orderBy('user_roles.office_id')
            ->first();

        return $pivot?->pivot?->office_id;
    }
}
