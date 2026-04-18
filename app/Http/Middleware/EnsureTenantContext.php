<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Company;
use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
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
        $resolved = $this->resolveCompanyId($request);

        // Authenticated but no tenant resolved → block data access with sentinel
        if ($resolved === null && $request->user() !== null) {
            $resolved = self::NO_TENANT_SENTINEL;
        }

        $this->tenantContext->setCompanyId($resolved);

        return $next($request);
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
                if ($user->hasRole('admin')) {
                    // Admins can access any existing company
                    if (Company::query()->whereKey($candidate)->exists()) {
                        return $candidate;
                    }
                } else {
                    // Non-admins must have an explicit user_companies assignment
                    $assigned = $user->companies()
                        ->wherePivot('company_id', $candidate)
                        ->exists();
                    if ($assigned) {
                        return $candidate;
                    }
                }
            }
        }

        // Fall back: first assigned company (highest is_default first), then driver's office company
        $defaultCompany = $user->companies()->first();
        if ($defaultCompany !== null) {
            return $defaultCompany->id;
        }

        $user->loadMissing('driver.office');

        return $user->driver?->office?->company_id;
    }
}
