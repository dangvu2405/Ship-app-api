<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class AttendanceController extends BaseController
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    public function index(Request $request): JsonResponse
    {
        if (! Schema::hasTable('attendances')) {
            return $this->successResponse($this->emptyPaginator($request), 'OK');
        }

        $query = DB::table('attendances')
            ->when(Schema::hasColumn('attendances', 'company_id'), fn ($q) => $q->where('company_id', $this->companyId()))
            ->when($request->integer('driver_id') > 0, fn ($q) => $q->where('driver_id', $request->integer('driver_id')))
            ->when($request->filled('date'), fn ($q) => $q->whereDate('date', $request->string('date')->toString()))
            ->orderByDesc('date')
            ->orderByDesc('id');

        return $this->successResponse($query->paginate($this->perPage($request)), 'OK');
    }

    public function checkIn(Request $request): JsonResponse
    {
        return $this->writeDisabledResponse($request, 'check_in');
    }

    public function checkOut(Request $request): JsonResponse
    {
        return $this->writeDisabledResponse($request, 'check_out');
    }

    public function adjust(Request $request, int $id): JsonResponse
    {
        if (! Schema::hasTable('attendances')) {
            return $this->notFoundResponse('Attendance module is not available.');
        }

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
            'check_in' => ['nullable', 'date'],
            'check_out' => ['nullable', 'date'],
            'status' => ['nullable', 'string', 'in:present,late,absent,partial'],
        ]);

        $query = DB::table('attendances')->where('id', $id);
        if (Schema::hasColumn('attendances', 'company_id')) {
            $query->where('company_id', $this->companyId());
        }

        if (! $query->exists()) {
            return $this->notFoundResponse('Attendance record not found.');
        }

        $updates = array_filter([
            'check_in' => $validated['check_in'] ?? null,
            'check_out' => $validated['check_out'] ?? null,
            'status' => $validated['status'] ?? null,
            'adjust_reason' => $validated['reason'],
            'updated_at' => now(),
        ], static fn ($value): bool => $value !== null);

        DB::table('attendances')->where('id', $id)->update($updates);

        $record = DB::table('attendances')->where('id', $id)->first();

        return $this->successResponse($record, 'OK');
    }

    public function lateList(Request $request): JsonResponse
    {
        if (! Schema::hasTable('attendances')) {
            return $this->successResponse($this->emptyPaginator($request), 'OK');
        }

        $request->merge(['status' => 'late']);

        return $this->index($request);
    }

    public function lateNotify(): JsonResponse
    {
        return $this->successResponse([
            'sent' => 0,
            'skipped' => true,
        ], 'OK');
    }

    private function writeDisabledResponse(Request $request, string $field): JsonResponse
    {
        if (! Schema::hasTable('attendances')) {
            return $this->successResponse([
                'driver_id' => $request->integer('driver_id') ?: null,
                'date' => now()->toDateString(),
                $field => now()->toDateTimeString(),
                'status' => 'present',
                'module_available' => false,
            ], 'OK');
        }

        return $this->errorResponse('Attendance write workflow requires the attendances table.', 422);
    }

    /**
     * @return array{data: array<int, never>, meta: array{current_page: int, last_page: int, per_page: int, total: int}}
     */
    private function emptyPaginator(Request $request): array
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

    private function companyId(): int
    {
        return (int) $this->tenantContext->getCompanyId();
    }

    private function perPage(Request $request): int
    {
        return min(max((int) $request->input('per_page', 15), 1), 100);
    }
}
