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

    public function handle(Request $request, Closure $next): Response
    {
        $this->tenantContext->setCompanyId($this->resolveCompanyId($request));

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

        $header = $request->header('X-Company-Id');
        $query = $request->query('company_id');
        $raw = $header !== null && $header !== '' ? $header : $query;

        if ($raw !== null && $raw !== '' && ctype_digit((string) $raw)) {
            $candidate = (int) $raw;

            if ($candidate > 0 && Company::query()->whereKey($candidate)->exists() && $user->hasRole('admin')) {
                return $candidate;
            }
        }

        $user->loadMissing('driver.office');

        return $user->driver?->office?->company_id;
    }
}
