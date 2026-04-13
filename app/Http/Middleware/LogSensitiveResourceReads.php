<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\AuditLog;
use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class LogSensitiveResourceReads
{
    public function __construct(
        private readonly TenantContext $tenantContext
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $request->isMethod('GET')) {
            return $response;
        }

        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
            return $response;
        }

        $path = ltrim($request->path(), '/');

        foreach ((array) config('ship.audit_read_path_patterns', []) as $pattern) {
            if (@preg_match($pattern, $path) === 1) {
                $this->writeReadAudit($request, $path);

                return $response;
            }
        }

        return $response;
    }

    private function writeReadAudit(Request $request, string $path): void
    {
        $user = $request->user();

        AuditLog::query()->create([
            'user_id' => $user?->id,
            'company_id' => $this->tenantContext->getCompanyId(),
            'action' => 'read',
            'table_name' => 'api',
            'resource' => $request->route()?->getName() ?? $path,
            'record_id' => null,
            'old_data' => null,
            'new_data' => [
                'path' => $path,
                'method' => $request->method(),
            ],
            'ip_address' => $request->ip(),
            'request_id' => $request->header('X-Request-Id'),
            'user_agent' => $this->truncate((string) $request->userAgent(), 512),
            'metadata' => [
                'query' => $request->query(),
            ],
        ]);
    }

    private function truncate(string $value, int $max): string
    {
        if (strlen($value) <= $max) {
            return $value;
        }

        return substr($value, 0, $max);
    }
}
