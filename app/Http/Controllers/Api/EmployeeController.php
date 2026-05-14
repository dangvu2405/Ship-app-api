<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Models\Driver;
use App\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class EmployeeController extends BaseController
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = Driver::query()
            ->where('company_id', $this->companyId())
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->when($request->filled('keyword'), function ($q) use ($request): void {
                $keyword = '%'.$request->string('keyword')->toString().'%';
                $q->where(function ($sub) use ($keyword): void {
                    $sub->where('name', 'like', $keyword)
                        ->orWhere('code', 'like', $keyword)
                        ->orWhere('email', 'like', $keyword)
                        ->orWhere('phone', 'like', $keyword);
                });
            })
            ->orderBy(
                in_array($request->input('sort_by'), ['id', 'code', 'name', 'status', 'created_at'], true)
                    ? $request->string('sort_by')->toString()
                    : 'name',
                $request->input('sort_order') === 'desc' ? 'desc' : 'asc',
            );

        $employees = $query->paginate($this->perPage($request))->through(static fn (Driver $driver): array => [
            'id' => $driver->id,
            'code' => $driver->code,
            'name' => $driver->name,
            'email' => $driver->email,
            'phone' => $driver->phone,
            'type' => 'driver',
            'status' => $driver->status,
            'office_id' => null,
            'expired_date' => $driver->expired_date?->toDateString(),
            'created_at' => $driver->created_at?->toJSON(),
            'updated_at' => $driver->updated_at?->toJSON(),
        ]);

        return $this->successResponse($employees, 'OK');
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
