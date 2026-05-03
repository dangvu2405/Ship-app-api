<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Models\Invoice;
use App\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DebtOverviewController extends BaseController
{
    public function __construct(private readonly TenantContext $tenantContext) {}

    public function index(Request $request): JsonResponse
    {
        $companyId = $this->tenantContext->getCompanyId();
        if ($companyId === null || $companyId < 1) {
            return $this->forbiddenResponse('api.forbidden');
        }

        $unpaid = Invoice::query()
            ->where('company_id', $companyId)
            ->whereIn('status', ['draft', 'issued'])
            ->selectRaw('COUNT(*) as cnt, COALESCE(SUM(total_amount),0) as total')
            ->first();

        return $this->successResponse([
            'unpaid_invoices' => (int) ($unpaid?->cnt ?? 0),
            'unpaid_total' => (string) ($unpaid?->total ?? '0.00'),
        ], 'api.common.ok');
    }
}
