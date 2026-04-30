<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Driver;
use App\Models\DriverWorkSchedule;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use InvalidArgumentException;

final class ScheduleService
{
    /**
     * Create a new draft schedule for a driver.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $actor): DriverWorkSchedule
    {
        $this->assertNoConflict(
            driverId: (int) $data['driver_id'],
            workDate: $data['work_date'],
            shiftCode: $data['shift_code'] ?? 'day',
            vehicleId: $data['vehicle_id'] ?? null,
            excludeId: null,
        );

        return DriverWorkSchedule::create([
            ...$data,
            'status' => 'draft',
            'submitted_by' => null,
        ]);
    }

    /**
     * Update a draft or submitted schedule.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(DriverWorkSchedule $schedule, array $data): DriverWorkSchedule
    {
        if ($schedule->isLocked()) {
            throw new InvalidArgumentException('Cannot update a locked schedule.');
        }

        if (isset($data['work_date']) || isset($data['shift_code']) || isset($data['vehicle_id'])) {
            $this->assertNoConflict(
                driverId: $schedule->driver_id,
                workDate: $data['work_date'] ?? $schedule->work_date->toDateString(),
                shiftCode: $data['shift_code'] ?? $schedule->shift_code,
                vehicleId: $data['vehicle_id'] ?? $schedule->vehicle_id,
                excludeId: $schedule->id,
            );
        }

        $schedule->update($data);

        return $schedule->fresh();
    }

    public function submit(DriverWorkSchedule $schedule, User $actor): DriverWorkSchedule
    {
        if (! in_array($schedule->status, ['draft'])) {
            throw new InvalidArgumentException('Only draft schedules can be submitted.');
        }

        $schedule->update([
            'status' => 'submitted',
            'submitted_by' => $actor->id,
            'submitted_at' => now(),
        ]);

        return $schedule->fresh();
    }

    /**
     * Approve a submitted schedule.
     *
     * @param  bool  $hosOverride  When true, bypass HOS violation and log the override reason.
     * @param  string  $overrideReason  Required when $hosOverride is true (e.g. "Emergency coverage approved by director").
     */
    public function approve(
        DriverWorkSchedule $schedule,
        User $actor,
        bool $hosOverride = false,
        string $overrideReason = '',
    ): DriverWorkSchedule {
        if ($schedule->status !== 'submitted') {
            throw new InvalidArgumentException('Only submitted schedules can be approved.');
        }

        // HOS check is mandatory per Nghị định 10/2020 (max 10h/day, min 8h rest).
        $hosViolation = $this->checkHos($schedule);
        if ($hosViolation !== null) {
            if (! $hosOverride) {
                throw new InvalidArgumentException(
                    "HOS violation: {$hosViolation}. To override, re-submit with hos_override=true and override_reason.",
                    422,
                );
            }

            if (trim($overrideReason) === '') {
                throw new InvalidArgumentException('override_reason is required when overriding an HOS violation.', 422);
            }

            // Log the override — mandatory audit trail for regulatory compliance.
            $this->auditLog($actor, 'schedule.hos_override', $schedule->id, [
                'hos_violation' => $hosViolation,
                'override_reason' => $overrideReason,
            ]);
        }

        $schedule->update([
            'status' => 'approved',
            'approved_by' => $actor->id,
            'approved_at' => now(),
            'hos_override_reason' => $hosOverride && $hosViolation ? $overrideReason : null,
        ]);

        return $schedule->fresh();
    }

    /**
     * Check Hours-of-Service constraints per Nghị định 10/2020/NĐ-CP:
     *   - Max 10 hours driving per day
     *   - Minimum 8 hours rest between consecutive shifts
     *
     * Returns a human-readable violation string, or null if compliant.
     */
    public function checkHos(DriverWorkSchedule $schedule): ?string
    {
        if ($schedule->start_time === null || $schedule->end_time === null) {
            return null; // Cannot validate without times
        }

        $dateStr = $schedule->work_date instanceof Carbon
            ? $schedule->work_date->toDateString()
            : (string) $schedule->work_date;

        $shiftStart = Carbon::parse("{$dateStr} {$schedule->start_time}");
        $shiftEnd = Carbon::parse("{$dateStr} {$schedule->end_time}");

        if ($shiftEnd->lte($shiftStart)) {
            $shiftEnd->addDay(); // overnight shift
        }

        $shiftHours = $shiftStart->floatDiffInHours($shiftEnd);

        // Rule 1: max 10 hours per shift (Điều 65 NĐ 10/2020)
        if ($shiftHours > 10.0) {
            return sprintf('Shift duration %.1fh exceeds 10h maximum (Điều 65 NĐ 10/2020)', $shiftHours);
        }

        // Rule 2: minimum 8 hours rest between consecutive approved/locked shifts
        $prevSchedule = DriverWorkSchedule::query()
            ->where('driver_id', $schedule->driver_id)
            ->where('work_date', '<=', $dateStr)
            ->where('id', '!=', $schedule->id)
            ->whereIn('status', ['approved', 'locked'])
            ->whereNotNull('end_time')
            ->orderByDesc('work_date')
            ->orderByDesc('end_time')
            ->first();

        if ($prevSchedule !== null) {
            $prevDateStr = $prevSchedule->work_date instanceof Carbon
                ? $prevSchedule->work_date->toDateString()
                : (string) $prevSchedule->work_date;

            $prevEnd = Carbon::parse("{$prevDateStr} {$prevSchedule->end_time}");

            // If previous shift's end appears to be before its start, it was overnight
            $prevStart = Carbon::parse("{$prevDateStr} {$prevSchedule->start_time}");
            if ($prevSchedule->start_time !== null && $prevEnd->lte($prevStart)) {
                $prevEnd->addDay();
            }

            $restHours = $prevEnd->floatDiffInHours($shiftStart);
            if ($restHours < 8.0) {
                return sprintf('Only %.1fh rest between shifts (minimum 8h required, NĐ 10/2020)', $restHours);
            }
        }

        return null;
    }

    public function reject(DriverWorkSchedule $schedule, User $actor): DriverWorkSchedule
    {
        if ($schedule->status !== 'submitted') {
            throw new InvalidArgumentException('Only submitted schedules can be rejected.');
        }

        $schedule->update(['status' => 'draft']);

        return $schedule->fresh();
    }

    /**
     * @param  array<string, mixed>  $filters  driver_id, office_id, work_date, from, to, status (optional keys)
     */
    public function paginateSchedulesForIndex(array $filters, int $per_page = 50): LengthAwarePaginator
    {
        $query = DriverWorkSchedule::query()->with(['driver', 'vehicle', 'office']);

        if (isset($filters['driver_id'])) {
            $query->forDriverId((int) $filters['driver_id']);
        }
        if (isset($filters['office_id'])) {
            $query->forOfficeId((int) $filters['office_id']);
        }
        if (isset($filters['work_date'])) {
            $query->forDate((string) $filters['work_date']);
        }
        if (isset($filters['from'], $filters['to'])) {
            $query->forPeriod((string) $filters['from'], (string) $filters['to']);
        }
        if (isset($filters['status'])) {
            $query->forStatusFilter((string) $filters['status']);
        }

        return $query->orderBy('work_date')->orderBy('shift_code')->paginate($per_page);
    }

    public function lockSingleRow(DriverWorkSchedule $schedule, User $actor): DriverWorkSchedule
    {
        if (! in_array($schedule->status, ['approved', 'submitted'], true)) {
            throw new InvalidArgumentException('Only submitted/approved schedules can be locked.', 422);
        }

        $schedule->update([
            'status' => 'locked',
            'locked_by' => $actor->id,
            'locked_at' => now(),
        ]);

        return $schedule->fresh();
    }

    /**
     * @param  array<string, mixed>  $payload  validated override fields (override_reason already stripped)
     */
    public function managerOverrideSchedule(DriverWorkSchedule $schedule, array $payload, string $override_reason): DriverWorkSchedule
    {
        $merged_notes = trim(($schedule->notes ?? '').' | OVERRIDE: '.$override_reason);
        $payload['notes'] = $merged_notes;

        $schedule->fill($payload);
        $schedule->status = 'approved';
        $schedule->locked_by = null;
        $schedule->locked_at = null;
        $schedule->save();

        return $schedule->fresh(['driver', 'vehicle', 'office']);
    }

    /**
     * Same-day total hours for non-draft rows (pre-check endpoint shape).
     *
     * @return array{driver_id: int, work_date: string, total_hours: float, limit_hours: float, is_ok: bool}
     */
    public function dailyHoursSummaryForRow(DriverWorkSchedule $anchor): array
    {
        $driver_id = (int) $anchor->driver_id;
        $work_date = $anchor->work_date instanceof Carbon
            ? $anchor->work_date->toDateString()
            : (string) $anchor->work_date;

        $hours = DriverWorkSchedule::query()
            ->where('driver_id', $driver_id)
            ->where('work_date', $work_date)
            ->whereNotIn('status', ['draft'])
            ->get()
            ->sum(static function (DriverWorkSchedule $row): float {
                $start = strtotime((string) $row->start_time);
                $end = strtotime((string) $row->end_time);
                if ($end < $start) {
                    $end += 86400;
                }

                return ($end - $start) / 3600;
            });

        $limit_hours = 12.0;
        $is_ok = $hours <= $limit_hours;

        return [
            'driver_id' => $driver_id,
            'work_date' => $work_date,
            'total_hours' => round($hours, 2),
            'limit_hours' => $limit_hours,
            'is_ok' => $is_ok,
        ];
    }

    public function destroyIfAllowed(DriverWorkSchedule $schedule): void
    {
        if ($schedule->isLocked()) {
            throw new InvalidArgumentException('Cannot delete a locked schedule.', 422);
        }

        $schedule->delete();
    }

    /**
     * Lock all approved schedules for an office+date range (called before payroll lock).
     */
    public function lockPeriod(int $officeId, string $from, string $to, User $actor): int
    {
        return DriverWorkSchedule::query()
            ->where('office_id', $officeId)
            ->where('status', 'approved')
            ->whereBetween('work_date', [$from, $to])
            ->update([
                'status' => 'locked',
                'locked_by' => $actor->id,
                'locked_at' => now(),
            ]);
    }

    /**
     * Throw a conflict exception if the driver or vehicle is already scheduled.
     */
    private function assertNoConflict(
        int $driverId,
        string $workDate,
        string $shiftCode,
        ?int $vehicleId,
        ?int $excludeId,
    ): void {
        // Check driver conflict: same driver + same date + same shift
        $driverConflict = DriverWorkSchedule::query()
            ->where('driver_id', $driverId)
            ->where('work_date', $workDate)
            ->where('shift_code', $shiftCode)
            ->when($excludeId !== null, fn ($q) => $q->where('id', '!=', $excludeId))
            ->first();

        if ($driverConflict) {
            throw new InvalidArgumentException(
                "Driver #{$driverId} already has an active schedule on {$workDate} for shift '{$shiftCode}'.",
                422,
            );
        }

        // Check vehicle conflict: same vehicle + same date (any shift — vehicle can't be in two places)
        if ($vehicleId !== null) {
            $vehicleConflict = DriverWorkSchedule::query()
                ->where('vehicle_id', $vehicleId)
                ->where('work_date', $workDate)
                ->whereNotIn('status', ['draft'])
                ->when($excludeId !== null, fn ($q) => $q->where('id', '!=', $excludeId))
                ->first();

            if ($vehicleConflict) {
                throw new InvalidArgumentException(
                    "Vehicle #{$vehicleId} is already assigned on {$workDate}.",
                    409,
                );
            }
        }
    }

    /** @param array<string, mixed> $metadata */
    private function auditLog(User $actor, string $action, int $recordId, array $metadata): void
    {
        try {
            AuditLog::create([
                'user_id' => $actor->id,
                'action' => $action,
                'table_name' => 'driver_work_schedules',
                'record_id' => $recordId,
                'new_data' => $metadata,
                'ip_address' => request()->ip(),
            ]);
        } catch (\Throwable) {
            // Non-fatal
        }
    }

    /**
     * Return approved working days count for a driver in a month (for proration).
     */
    public function approvedWorkingDays(int $driverId, int $year, int $month): int
    {
        return DriverWorkSchedule::query()
            ->where('driver_id', $driverId)
            ->whereIn('status', ['approved', 'locked'])
            ->whereYear('work_date', $year)
            ->whereMonth('work_date', $month)
            ->count();
    }
}
