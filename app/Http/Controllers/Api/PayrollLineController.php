<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Models\PayrollLine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PayrollLineController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        $query = PayrollLine::query()
            ->with(['payroll', 'driver'])
            ->whereHas('payroll', function ($q) {
                $q->where('company_id', $this->getCompanyId());
            });

        if ($request->filled('payroll_id')) {
            $query->where('payroll_id', $request->input('payroll_id'));
        }

        if ($request->filled('driver_id')) {
            $query->where('driver_id', $request->input('driver_id'));
        }

        $perPage = (int) $request->input('per_page', 15);
        $lines = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $lines->items(),
            'meta' => [
                'current_page' => $lines->currentPage(),
                'last_page' => $lines->lastPage(),
                'per_page' => $lines->perPage(),
                'total' => $lines->total(),
            ],
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $line = PayrollLine::with(['payroll', 'driver'])->find($id);

        if (!$line || $line->payroll->company_id !== $this->getCompanyId()) {
            return $this->notFoundResponse('Payroll line not found');
        }

        return $this->successResponse($line);
    }

    private function getCompanyId(): ?int
    {
        return app(\App\Tenancy\TenantContext::class)->getCompanyId();
    }
}
