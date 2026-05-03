<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\AuditLog;
use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class PermissionMiddleware
{
    public function __construct(
        private readonly TenantContext $tenantContext
    ) {}

    public function handle(Request $request, Closure $next, string $permission): Response
    {
        if (! auth()->check()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
            ], 401);
        }

        $user = auth()->user();
        $companyId = $this->tenantContext->getCompanyId();

        // Sentinel -1 means authenticated but no company resolved; treat as null (global check).
        $permissionCompanyId = ($companyId !== null && $companyId > 0) ? $companyId : null;

        if (! $user->hasPermission($permission, $permissionCompanyId)) {
            $this->logAuthzFailure($request, $user->id, "permission:{$permission}", $permissionCompanyId);

            return response()->json([
                'success' => false,
                'message' => 'Forbidden: Insufficient permission',
            ], 403);
        }

        return $next($request);
    }

    private function logAuthzFailure(Request $request, int $userId, string $required, ?int $companyId): void
    {
        try {
            AuditLog::create([
                'user_id' => $userId,
                'company_id' => $companyId,
                'action' => 'authz_failure',
                'table_name' => 'permissions',
                'resource' => $required,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'metadata' => [
                    'route' => $request->path(),
                    'method' => $request->method(),
                ],
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to write authz_failure audit log', ['error' => $e->getMessage()]);
        }
    }
}
