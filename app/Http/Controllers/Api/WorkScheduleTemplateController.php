<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\WorkScheduleTemplate\StoreWorkScheduleTemplateRequest;
use App\Http\Requests\WorkScheduleTemplate\UpdateWorkScheduleTemplateRequest;
use App\Models\WorkScheduleTemplate;
use App\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

final class WorkScheduleTemplateController extends BaseController
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    /**
     * GET /api/work-schedule-templates?company_id=&include_inactive=
     */
    public function index(Request $request): JsonResponse
    {
        $companyId = $this->resolveCompanyIdForWrite($request);
        if ($companyId === null) {
            return $this->errorResponse('api.work_schedule_template.company_context_missing', 422);
        }

        $query = WorkScheduleTemplate::query()
            ->where('company_id', $companyId)
            ->orderBy('name');

        if (! $request->boolean('include_inactive')) {
            $query->where('is_active', true);
        }

        $templates = $query->get([
            'id', 'company_id', 'name', 'shift_code', 'start_time', 'end_time', 'description', 'is_active', 'created_at', 'updated_at',
        ]);

        return $this->successResponse(['templates' => $templates], 'api.common.ok');
    }

    /**
     * POST /api/work-schedule-templates
     */
    public function store(StoreWorkScheduleTemplateRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            $companyId = $validated['company_id'] ?? $this->tenantContext->getCompanyId();
            if ($companyId === null || $companyId < 1) {
                return $this->errorResponse('api.work_schedule_template.company_context_missing_short', 422);
            }

            unset($validated['company_id']);

            $template = WorkScheduleTemplate::query()->create([
                ...$validated,
                'company_id' => $companyId,
                'is_active' => (bool) ($validated['is_active'] ?? true),
            ]);

            return $this->successResponse($template->fresh(), 'api.work_schedule_template.created', 201);
        } catch (Throwable $e) {
            return $this->handleException($e);
        }
    }

    /**
     * GET /api/work-schedule-templates/{work_schedule_template}
     */
    public function show(Request $request, WorkScheduleTemplate $work_schedule_template): JsonResponse
    {
        if (! $this->templateBelongsToTenant($request, $work_schedule_template)) {
            return $this->errorResponse('api.work_schedule_template.not_found', 404);
        }

        return $this->successResponse($work_schedule_template, 'api.common.ok');
    }

    /**
     * PUT/PATCH /api/work-schedule-templates/{work_schedule_template}
     */
    public function update(UpdateWorkScheduleTemplateRequest $request, WorkScheduleTemplate $work_schedule_template): JsonResponse
    {
        try {
            if (! $this->templateBelongsToTenant($request, $work_schedule_template)) {
                return $this->errorResponse('api.work_schedule_template.not_found', 404);
            }

            $work_schedule_template->update($request->validated());

            return $this->successResponse($work_schedule_template->fresh(), 'api.work_schedule_template.updated');
        } catch (Throwable $e) {
            return $this->handleException($e);
        }
    }

    /**
     * DELETE /api/work-schedule-templates/{work_schedule_template}
     */
    public function destroy(Request $request, WorkScheduleTemplate $work_schedule_template): JsonResponse
    {
        try {
            if (! $this->templateBelongsToTenant($request, $work_schedule_template)) {
                return $this->errorResponse('api.work_schedule_template.not_found', 404);
            }

            $work_schedule_template->delete();

            return $this->successResponse(null, 'api.work_schedule_template.deleted');
        } catch (Throwable $e) {
            return $this->handleException($e);
        }
    }

    private function resolveCompanyIdForWrite(Request $request): ?int
    {
        if ($request->filled('company_id')) {
            return (int) $request->query('company_id');
        }

        $id = $this->tenantContext->getCompanyId();

        return ($id !== null && $id > 0) ? $id : null;
    }

    private function templateBelongsToTenant(Request $request, WorkScheduleTemplate $template): bool
    {
        $companyId = $this->resolveCompanyIdForWrite($request);
        if ($companyId === null) {
            return false;
        }

        return (int) $template->company_id === (int) $companyId;
    }
}
