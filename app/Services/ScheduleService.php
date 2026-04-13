<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Driver;
use App\Models\DriverWorkSchedule;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ScheduleService
{
    /**
     * Create a new draft schedule for a driver.
     *
     * @param array<string, mixed> $data
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
            'status'       => 'draft',
            'submitted_by' => null,
        ]);
    }

    /**
     * Update a draft or submitted schedule.
     *
     * @param array<string, mixed> $data
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
            'status'       => 'submitted',
            'submitted_by' => $actor->id,
            'submitted_at' => now(),
        ]);

        return $schedule->fresh();
    }

    public function approve(DriverWorkSchedule $schedule, User $actor): DriverWorkSchedule
    {
        if ($schedule->status !== 'submitted') {
            throw new InvalidArgumentException('Only submitted schedules can be approved.');
        }

        $schedule->update([
            'status'      => 'approved',
            'approved_by' => $actor->id,
            'approved_at' => now(),
        ]);

        return $schedule->fresh();
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
     * Lock all approved schedules for an office+date range (called before payroll lock).
     */
    public function lockPeriod(int $officeId, string $from, string $to, User $actor): int
    {
        return DriverWorkSchedule::query()
            ->where('office_id', $officeId)
            ->where('status', 'approved')
            ->whereBetween('work_date', [$from, $to])
            ->update([
                'status'     => 'locked',
                'locked_by'  => $actor->id,
                'locked_at'  => now(),
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
            ->whereNotIn('status', ['draft'])
            ->when($excludeId !== null, fn ($q) => $q->where('id', '!=', $excludeId))
            ->first();

        if ($driverConflict) {
            throw new InvalidArgumentException(
                "Driver #{$driverId} already has an active schedule on {$workDate} for shift '{$shiftCode}'.",
                409,
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
