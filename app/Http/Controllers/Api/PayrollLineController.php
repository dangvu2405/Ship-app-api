<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Models\PayrollLine;
use App\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class PayrollLineController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        if (! Schema::hasTable('payroll_lines')) {
            return $this->successResponse($this->emptyPaginatedData($request), 'OK');
        }

        $query = PayrollLine::query()
            ->with(['payroll', 'driver'])
            ->whereHas('payroll', function ($q) use ($request) {
                $q->where('company_id', $this->getCompanyId());

                if ($request->filled('month')) {
                    $q->where('month', (int) $request->input('month'));
                }

                if ($request->filled('year')) {
                    $q->where('year', (int) $request->input('year'));
                }
            });

        if ($request->filled('payroll_id')) {
            $query->where('payroll_id', $request->input('payroll_id'));
        }

        if ($request->filled('driver_id')) {
            $query->where('driver_id', $request->input('driver_id'));
        }

        $perPage = $this->perPage($request);
        $lines = $query->paginate($perPage);

        return $this->successResponse($lines, 'OK');
    }

    public function show(string $id): JsonResponse
    {
        if (! Schema::hasTable('payroll_lines')) {
            return $this->notFoundResponse('Payroll line not found');
        }

        $line = PayrollLine::with(['payroll', 'driver'])->find($id);

        if (! $line || $line->payroll?->company_id !== $this->getCompanyId()) {
            return $this->notFoundResponse('Payroll line not found');
        }

        return $this->successResponse($line);
    }

    private function getCompanyId(): ?int
    {
        return app(TenantContext::class)->getCompanyId();
    }

    private function perPage(Request $request): int
    {
        $perPage = (int) $request->input('per_page', 15);

        return min(max($perPage, 1), 100);
    }

    /**
     * Some deployed databases have payroll module tables disabled.
     * Read endpoints should remain stable while write/generate flows surface schema readiness separately.
     *
     * @return array{data: array<int, never>, meta: array{current_page: int, last_page: int, per_page: int, total: int}}
     */
    private function emptyPaginatedData(Request $request): array
    {
        return [
            'data' => [],
            'meta' => [
                'current_page' => 1,
                'last_page' => 1,
                'per_page' => $this->perPage($request),
                'total' => 0,
            ],
        ];
    }
}
