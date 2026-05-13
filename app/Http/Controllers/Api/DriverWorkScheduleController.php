<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Models\Driver;
use App\Models\DriverWorkSchedule;
use App\Models\Vehicle;
use App\Services\ScheduleService;
use App\Tenancy\TenantContext;
use Carbon\CarbonPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

final class DriverWorkScheduleController extends BaseController
{
    public function __construct(
        private readonly ScheduleService $scheduleService,
        private readonly TenantContext $tenantContext,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $companyId = $this->companyId($request);

        $query = DriverWorkSchedule::query()
            ->with(['driver:id,code,name', 'vehicle:id,plate_number'])
            ->where('company_id', $companyId)
            ->when($request->integer('driver_id') > 0, fn ($q) => $q->where('driver_id', $request->integer('driver_id')))
            ->when($request->integer('vehicle_id') > 0, fn ($q) => $q->where('vehicle_id', $request->integer('vehicle_id')))
            ->when($request->filled('work_date'), fn ($q) => $q->where('work_date', $request->string('work_date')->toString()))
            ->when($request->filled('from') && $request->filled('to'), fn ($q) => $q->whereBetween('work_date', [
                $request->string('from')->toString(),
                $request->string('to')->toString(),
            ]))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->orderBy('work_date')
            ->orderBy('start_time');

        return $this->successResponse($query->paginate($this->perPage($request)), 'OK');
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validateSchedule($request);
        $companyId = $this->companyId($request);
        $tenantError = $this->validateTenantReferences($validated, $companyId);
        if ($tenantError !== null) {
            return $tenantError;
        }

        try {
            $schedule = $this->scheduleService->create(array_merge($validated, ['company_id' => $companyId]), $request->user());
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), $this->exceptionStatus($e));
        }

        return $this->successResponse($schedule->load(['driver:id,code,name', 'vehicle:id,plate_number']), 'OK', 201);
    }

    public function show(Request $request, DriverWorkSchedule $driverWorkSchedule): JsonResponse
    {
        if (! $this->belongsToCompany($driverWorkSchedule, $request)) {
            return $this->forbiddenResponse();
        }

        return $this->successResponse($driverWorkSchedule->load(['driver:id,code,name', 'vehicle:id,plate_number']), 'OK');
    }

    public function update(Request $request, DriverWorkSchedule $driverWorkSchedule): JsonResponse
    {
        if (! $this->belongsToCompany($driverWorkSchedule, $request)) {
            return $this->forbiddenResponse();
        }

        $validated = $this->validateSchedule($request, true);
        $tenantError = $this->validateTenantReferences($validated, $this->companyId($request));
        if ($tenantError !== null) {
            return $tenantError;
        }

        try {
            $schedule = $this->scheduleService->update($driverWorkSchedule, $validated);
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), $this->exceptionStatus($e));
        }

        return $this->successResponse($schedule->load(['driver:id,code,name', 'vehicle:id,plate_number']), 'OK');
    }

    public function destroy(Request $request, DriverWorkSchedule $driverWorkSchedule): Response|JsonResponse
    {
        if (! $this->belongsToCompany($driverWorkSchedule, $request)) {
            return $this->forbiddenResponse();
        }

        try {
            $this->scheduleService->destroyIfAllowed($driverWorkSchedule);
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), $this->exceptionStatus($e));
        }

        return response()->noContent();
    }

    public function generate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'driver_ids' => ['nullable', 'array'],
            'driver_ids.*' => ['integer'],
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
            'shift_code' => ['nullable', 'string', 'max:20'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'skip_weekends' => ['nullable', 'boolean'],
        ]);

        $companyId = $this->companyId($request);
        $driverIds = $validated['driver_ids'] ?? Driver::query()
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->pluck('id')
            ->all();

        $created = 0;
        $skipped = 0;
        $period = CarbonPeriod::create($validated['from'], $validated['to']);

        DB::transaction(function () use ($validated, $companyId, $driverIds, $period, &$created, &$skipped): void {
            foreach ($period as $date) {
                if (($validated['skip_weekends'] ?? false) && $date->isWeekend()) {
                    continue;
                }

                foreach ($driverIds as $driverId) {
                    if (! Driver::query()->where('company_id', $companyId)->whereKey($driverId)->exists()) {
                        $skipped++;

                        continue;
                    }

                    $exists = DriverWorkSchedule::query()
                        ->where('company_id', $companyId)
                        ->where('driver_id', $driverId)
                        ->where('work_date', $date->toDateString())
                        ->where('shift_code', $validated['shift_code'] ?? 'day')
                        ->exists();

                    if ($exists) {
                        $skipped++;

                        continue;
                    }

                    DriverWorkSchedule::query()->create([
                        'company_id' => $companyId,
                        'driver_id' => $driverId,
                        'work_date' => $date->toDateString(),
                        'shift_code' => $validated['shift_code'] ?? 'day',
                        'start_time' => $validated['start_time'] ?? '08:00',
                        'end_time' => $validated['end_time'] ?? '17:00',
                        'status' => 'draft',
                        'submitted_by' => null,
                    ]);
                    $created++;
                }
            }
        });

        return $this->successResponse(['created' => $created, 'skipped' => $skipped], 'OK', 201);
    }

    public function submit(Request $request, DriverWorkSchedule $driverWorkSchedule): JsonResponse
    {
        return $this->mutate($request, $driverWorkSchedule, fn () => $this->scheduleService->submit($driverWorkSchedule, $request->user()));
    }

    public function approve(Request $request, DriverWorkSchedule $driverWorkSchedule): JsonResponse
    {
        $validated = $request->validate([
            'hos_override' => ['nullable', 'boolean'],
            'override_reason' => ['nullable', 'string', 'max:500'],
        ]);

        return $this->mutate($request, $driverWorkSchedule, fn () => $this->scheduleService->approve(
            $driverWorkSchedule,
            $request->user(),
            (bool) ($validated['hos_override'] ?? false),
            (string) ($validated['override_reason'] ?? ''),
        ));
    }

    public function reject(Request $request, DriverWorkSchedule $driverWorkSchedule): JsonResponse
    {
        return $this->mutate($request, $driverWorkSchedule, fn () => $this->scheduleService->reject($driverWorkSchedule, $request->user()));
    }

    public function lock(Request $request, DriverWorkSchedule $driverWorkSchedule): JsonResponse
    {
        return $this->mutate($request, $driverWorkSchedule, fn () => $this->scheduleService->lockSingleRow($driverWorkSchedule, $request->user()));
    }

    public function override(Request $request, DriverWorkSchedule $driverWorkSchedule): JsonResponse
    {
        $validated = $request->validate([
            'override_reason' => ['required', 'string', 'max:500'],
            'driver_id' => ['sometimes', 'integer'],
            'vehicle_id' => ['sometimes', 'nullable', 'integer'],
            'work_date' => ['sometimes', 'date'],
            'shift_code' => ['sometimes', 'string', 'max:20'],
            'start_time' => ['sometimes', 'date_format:H:i'],
            'end_time' => ['sometimes', 'date_format:H:i', 'after:start_time'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ]);

        $reason = (string) $validated['override_reason'];
        unset($validated['override_reason']);

        return $this->mutate($request, $driverWorkSchedule, fn () => $this->scheduleService->managerOverrideSchedule($driverWorkSchedule, $validated, $reason));
    }

    public function hosCheck(Request $request, DriverWorkSchedule $driverWorkSchedule): JsonResponse
    {
        if (! $this->belongsToCompany($driverWorkSchedule, $request)) {
            return $this->forbiddenResponse();
        }

        $reason = $this->scheduleService->checkHos($driverWorkSchedule);

        return $this->successResponse([
            'allowed' => $reason === null,
            'reason' => $reason,
            'driving_hours_today' => $this->scheduleService->dailyHoursSummaryForRow($driverWorkSchedule)['total_hours'],
        ], 'OK');
    }

    private function mutate(Request $request, DriverWorkSchedule $schedule, callable $callback): JsonResponse
    {
        if (! $this->belongsToCompany($schedule, $request)) {
            return $this->forbiddenResponse();
        }

        try {
            $result = $callback();
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), $this->exceptionStatus($e));
        }

        return $this->successResponse($result->load(['driver:id,code,name', 'vehicle:id,plate_number']), 'OK');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateSchedule(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'driver_id' => [$required, 'integer'],
            'vehicle_id' => ['nullable', 'integer'],
            'work_date' => [$required, 'date'],
            'shift_code' => ['nullable', 'string', 'max:20'],
            'start_time' => [$required, 'date_format:H:i'],
            'end_time' => [$required, 'date_format:H:i', 'after:start_time'],
            'status' => ['sometimes', Rule::in(['draft', 'submitted', 'approved', 'locked'])],
            'notes' => ['nullable', 'string'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function validateTenantReferences(array $validated, int $companyId): ?JsonResponse
    {
        if (isset($validated['driver_id']) && ! Driver::query()->where('company_id', $companyId)->whereKey($validated['driver_id'])->exists()) {
            return $this->validationErrorResponse(['driver_id' => ['Driver is invalid.']]);
        }

        if (isset($validated['vehicle_id']) && $validated['vehicle_id'] !== null && ! Vehicle::query()->where('company_id', $companyId)->whereKey($validated['vehicle_id'])->exists()) {
            return $this->validationErrorResponse(['vehicle_id' => ['Vehicle is invalid.']]);
        }

        return null;
    }

    private function belongsToCompany(DriverWorkSchedule $schedule, Request $request): bool
    {
        return (int) $schedule->company_id === $this->companyId($request);
    }

    private function companyId(Request $request): int
    {
        return (int) ($this->tenantContext->getCompanyId() ?? $request->user()?->getAttribute('company_id'));
    }

    private function perPage(Request $request): int
    {
        return min(max($request->integer('per_page', 50), 1), 200);
    }

    private function exceptionStatus(InvalidArgumentException $e): int
    {
        $code = (int) $e->getCode();

        return in_array($code, [409, 422], true) ? $code : 422;
    }
}
