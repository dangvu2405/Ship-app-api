<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\AuditLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class TrackUserActions
{
    /** @var array<string, bool>|null */
    private static ?array $auditLogColumns = null;

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        try {
            if (! $request->is('api/*')) {
                return $response;
            }

            $user = $request->user();
            if ($user === null) {
                return $response;
            }

            // Avoid recursively logging auth log retrieval endpoints.
            if ($request->is('api/v1/auth/logs*')) {
                return $response;
            }

            $resource = '/'.$request->path();
            [$tableName, $recordId, $entityType] = $this->resolveResourceInfo($resource);

            $payload = [
                'user_id' => $user->id,
                'action' => sprintf('%s %s', strtoupper($request->method()), $resource),
                'table_name' => $tableName,
                'record_id' => $recordId,
                'new_data' => [
                    'status_code' => $response->getStatusCode(),
                ],
                'ip_address' => $request->ip(),
            ];

            $columns = $this->auditLogColumns();
            if (($columns['company_id'] ?? false) === true) {
                $payload['company_id'] = $user->driver?->company_id;
            }
            if (($columns['resource'] ?? false) === true) {
                $payload['resource'] = $resource;
            }
            if (($columns['request_id'] ?? false) === true) {
                $payload['request_id'] = (string) ($request->headers->get('X-Request-ID') ?? $request->header('X-Request-Id') ?? '');
            }
            if (($columns['user_agent'] ?? false) === true) {
                $payload['user_agent'] = substr((string) $request->userAgent(), 0, 512);
            }
            if (($columns['metadata'] ?? false) === true) {
                $payload['metadata'] = [
                    'query' => $request->query(),
                    'entity_type' => $entityType,
                ];
            }

            AuditLog::query()->create($payload);
        } catch (\Throwable $exception) {
            // Never block API responses if audit tracking has issues.
            Log::warning('TrackUserActions failed to persist audit log.', [
                'message' => $exception->getMessage(),
                'path' => $request->path(),
            ]);
        }

        return $response;
    }

    /**
     * @return array<string, bool>
     */
    private function auditLogColumns(): array
    {
        if (self::$auditLogColumns !== null) {
            return self::$auditLogColumns;
        }

        $columns = Schema::getColumnListing('audit_logs');
        self::$auditLogColumns = array_fill_keys($columns, true);

        return self::$auditLogColumns;
    }

    /**
     * @return array{0: string, 1: int|null, 2: string}
     */
    private function resolveResourceInfo(string $resource): array
    {
        $trimmedPath = trim($resource, '/');
        $segments = $trimmedPath === '' ? [] : explode('/', $trimmedPath);

        if (($segments[0] ?? '') === 'api') {
            array_shift($segments);
        }
        if (($segments[0] ?? '') === 'v1') {
            array_shift($segments);
        }

        $tableName = $segments[0] ?? 'api_requests';
        $recordId = null;
        foreach ($segments as $segment) {
            if (ctype_digit($segment)) {
                $recordId = (int) $segment;
                break;
            }
        }

        $entityType = Str::singular(str_replace('-', '_', $tableName));

        return [$tableName, $recordId, $entityType];
    }
}
