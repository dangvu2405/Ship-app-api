<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\Office\ApplyOfficeScheduleRequest;
use App\Jobs\ApplyOfficeScheduleJob;
use App\Models\Office;
use App\Models\WorkScheduleTemplate;
use App\Services\ApplyOfficeScheduleService;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;
use Throwable;

final class OfficeApplyScheduleController extends BaseController
{
    public function __construct(
        private readonly ApplyOfficeScheduleService $applyOfficeScheduleService,
    ) {}

    /**
     * POST /api/v1/offices/{office}/apply-schedule
     * Áp khung giờ (template) cho toàn bộ tài xế thuộc văn phòng trong khoảng ngày.
     */
    public function store(ApplyOfficeScheduleRequest $request, Office $office): JsonResponse
    {
        try {
            $validated = $request->validated();
            $template = WorkScheduleTemplate::query()->findOrFail((int) $validated['schedule_id']);

            $estimate = $this->applyOfficeScheduleService->estimateBulkRows(
                $office,
                (string) $validated['start_date'],
                (string) $validated['end_date'],
            );
            $syncMaxRows = (int) config('ship.office_schedule_apply.sync_max_rows', 8000);

            if ($estimate['row_count'] > $syncMaxRows) {
                ApplyOfficeScheduleJob::dispatch(
                    officeId: $office->id,
                    templateId: $template->id,
                    startDate: (string) $validated['start_date'],
                    endDate: (string) $validated['end_date'],
                    actorUserId: (int) $request->user()->id,
                    notes: isset($validated['notes']) ? (string) $validated['notes'] : null,
                    replaceDrafts: (bool) ($validated['replace_drafts'] ?? true),
                );

                return $this->successResponse([
                    'queued' => true,
                    'estimated_rows' => $estimate['row_count'],
                    'sync_max_rows' => $syncMaxRows,
                    'driver_count' => $estimate['driver_count'],
                    'day_count' => $estimate['day_count'],
                ], 'Khối lượng lớn: đã đưa vào hàng đợi xử lý. Kiểm tra queue worker.', 202);
            }

            $result = $this->applyOfficeScheduleService->apply(
                office: $office,
                template: $template,
                startDate: (string) $validated['start_date'],
                endDate: (string) $validated['end_date'],
                actor: $request->user(),
                notes: isset($validated['notes']) ? (string) $validated['notes'] : null,
                replaceDrafts: (bool) ($validated['replace_drafts'] ?? true),
            );

            return $this->successResponse($result, 'Đã áp dụng lịch làm việc cho văn phòng.', 201);
        } catch (InvalidArgumentException $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 422;

            return $this->errorResponse($e->getMessage(), $code);
        } catch (Throwable $e) {
            return $this->handleException($e);
        }
    }
}
