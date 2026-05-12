<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class ActivityLogsController extends BaseController
{
    public function __construct(private readonly TenantContext $tenantContext) {}

    public function index(Request $request): JsonResponse
    {
        $companyId = $this->tenantContext->getCompanyId();
        if ($companyId === null) {
            return $this->forbiddenResponse('api.forbidden');
        }

        if (!Schema::hasTable('activity_log')) {
            return $this->successResponse([], 'api.common.ok');
        }

        $page = $request->query('page', 1);
        $per_page = min((int) $request->query('per_page', 50), 100);
        $type = $request->query('type');
        $user_id = $request->query('user_id');
        $subject_type = $request->query('subject_type');

        $query = DB::table('activity_log')
            ->where('properties->company_id', $companyId)
            ->orderByDesc('created_at');

        if ($type) {
            $query->where('description', $type);
        }
        if ($user_id) {
            $query->where('causer_id', $user_id);
        }
        if ($subject_type) {
            $query->where('subject_type', $subject_type);
        }

        $total = $query->count();
        $logs = $query
            ->skip(($page - 1) * $per_page)
            ->take($per_page)
            ->get();

        return $this->successResponse([
            'data' => $logs,
            'pagination' => [
                'total' => $total,
                'per_page' => $per_page,
                'current_page' => $page,
                'last_page' => ceil($total / $per_page),
            ],
        ], 'api.common.ok');
    }
}
