<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\AuditLog;
use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Role-based access guard using the CETA `users.role` enum.
 *
 * Usage in routes:
 *   ->middleware('role:admin')           // global admin only
 *   ->middleware('role:dispatcher')      // dispatcher OR admin
 *   ->middleware('role:accountant')      // accountant OR admin
 */
class RoleMiddleware
{
    public function __construct(
        private readonly TenantContext $tenantContext
    ) {}

    public function handle(Request $request, Closure $next, string $role): Response
    {
        $user = $request->user();
        if ($user === null) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
            ], 401);
        }
        $companyId = $this->tenantContext->getCompanyId();

        // Sentinel -1 means authenticated but no company resolved.
        // Pass null so hasRole() checks only the users.role enum.
        $roleCompanyId = ($companyId !== null && $companyId > 0) ? $companyId : null;

        $allowed = match ($role) {
            // Only the global system admin
            'admin' => $user->hasRole('admin') || $user->hasRole('super_admin'),

            // company_admin or higher
            'company_admin' => $user->hasRole('admin')
                || $user->hasRole('super_admin')
                || $user->hasRole('company_admin', $roleCompanyId),

            // office_admin or higher
            'office_admin' => $user->hasRole('admin')
                || $user->hasRole('super_admin')
                || $user->hasRole('company_admin', $roleCompanyId)
                || $user->hasRole('office_admin', $roleCompanyId),

            // Exact match for any other custom role, scoped to current company
            default => $user->hasRole('admin')
                || $user->hasRole('super_admin')
                || $user->hasRole($role, $roleCompanyId),
        };

        if (! $allowed) {
            $this->logAuthzFailure($request, $user->id, "role:{$role}", $roleCompanyId);

            return response()->json([
                'success' => false,
                'message' => 'Forbidden: Insufficient role',
            ], 403);
        }

        return $next($request);
    }

    private function logAuthzFailure(Request $request, int $userId, string $required, ?int $companyId): void
    {
        try {
            AuditLog::create([
                'user_id'    => $userId,
                'company_id' => $companyId,
                'action'     => 'authz_failure',
                'table_name' => 'roles',
                'resource'   => $required,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'metadata'   => [
                    'route'  => $request->path(),
                    'method' => $request->method(),
                ],
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to write authz_failure audit log', ['error' => $e->getMessage()]);
        }
    }
}
