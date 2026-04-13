<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditLog;
use App\Models\OvertimeRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class OvertimeService
{
    /**
     * Maximum OT hours allowed per month (Vietnamese Labor Code: 40h/month).
     */
    private const MAX_OT_HOURS_PER_MONTH = 40.0;

    /**
     * Submit an overtime request for a driver.
     *
     * @param array<string, mixed> $data
     */
    public function request(array $data, User $actor): OvertimeRequest
    {
        $workDate = $data['work_date'];
        $driverId = (int) $data['driver_id'];

        // Enforce monthly OT cap
        [$year, $month] = explode('-', $workDate);
        $existingMonthlyHours = $this->totalApprovedOtHoursForMonth($driverId, (int) $year, (int) $month);
        $newHours = (float) $data['ot_hours'];

        if ($existingMonthlyHours + $newHours > self::MAX_OT_HOURS_PER_MONTH) {
            throw new InvalidArgumentException(
                sprintf(
                    'Monthly OT cap of %.0f hours exceeded. Driver has %.2f approved hours this month.',
                    self::MAX_OT_HOURS_PER_MONTH,
                    $existingMonthlyHours,
                ),
            );
        }

        return DB::transaction(function () use ($data, $actor): OvertimeRequest {
            $ot = OvertimeRequest::create([
                ...$data,
                'status'       => 'pending',
                'requested_by' => $actor->id,
            ]);

            $this->auditLog($actor, 'overtime.requested', $ot->id, null, $ot->toArray());

            return $ot->load('driver');
        });
    }

    /**
     * Approve an OT request. Enforces SoD: approver must not be the requester.
     */
    public function approve(OvertimeRequest $ot, User $actor): OvertimeRequest
    {
        if ($ot->requested_by === $actor->id) {
            throw new InvalidArgumentException(
                'Separation of Duties: the requester cannot approve their own overtime request.',
                403,
            );
        }

        if ($ot->status !== 'pending') {
            throw new InvalidArgumentException("Cannot approve OT request in status '{$ot->status}'.");
        }

        return DB::transaction(function () use ($ot, $actor): OvertimeRequest {
            $before = $ot->toArray();
            $ot->update([
                'status'      => 'approved',
                'approved_by' => $actor->id,
                'approved_at' => now(),
            ]);
            $this->auditLog($actor, 'overtime.approved', $ot->id, $before, $ot->fresh()->toArray());

            return $ot->fresh(['driver', 'approver']);
        });
    }

    /**
     * Reject an OT request.
     */
    public function reject(OvertimeRequest $ot, string $reason, User $actor): OvertimeRequest
    {
        if ($ot->status !== 'pending') {
            throw new InvalidArgumentException("Cannot reject OT request in status '{$ot->status}'.");
        }

        $before = $ot->toArray();
        $ot->update([
            'status'           => 'rejected',
            'rejection_reason' => $reason,
        ]);
        $this->auditLog($actor, 'overtime.rejected', $ot->id, $before, $ot->fresh()->toArray());

        return $ot->fresh('driver');
    }

    /**
     * Calculate total OT pay for a driver in a period.
     * Rates per Vietnamese Labor Code:
     *   - Weekday OT: 150% of hourly rate
     *   - Weekend OT: 200%
     *   - Public holiday OT: 300%
     *
     * @param array<string> $holidayDates
     */
    public function calculateOtPay(
        int $driverId,
        string $from,
        string $to,
        float $monthlyBaseSalary,
        array $holidayDates = [],
    ): float {
        $standardDays   = 26.0;
        $standardHours  = $standardDays * 8;
        $hourlyRate     = $monthlyBaseSalary / $standardHours;

        $otRequests = OvertimeRequest::query()
            ->where('driver_id', $driverId)
            ->where('status', 'approved')
            ->whereBetween('work_date', [$from, $to])
            ->get();

        $totalPay = 0.0;

        foreach ($otRequests as $ot) {
            $dateStr  = $ot->work_date->toDateString();
            $dayOfWeek = $ot->work_date->dayOfWeek;
            $isHoliday = in_array($dateStr, $holidayDates);
            $isWeekend = in_array($dayOfWeek, [0, 6]);

            $multiplier = match (true) {
                $isHoliday => 3.0,
                $isWeekend => 2.0,
                default    => 1.5,
            };

            $totalPay += (float) $ot->ot_hours * $hourlyRate * $multiplier;
        }

        return round($totalPay, 0);
    }

    public function totalApprovedOtHoursForMonth(int $driverId, int $year, int $month): float
    {
        return (float) OvertimeRequest::query()
            ->where('driver_id', $driverId)
            ->where('status', 'approved')
            ->whereYear('work_date', $year)
            ->whereMonth('work_date', $month)
            ->sum('ot_hours');
    }

    /** @param array<string, mixed>|null $before @param array<string, mixed> $after */
    private function auditLog(User $actor, string $action, int $recordId, ?array $before, array $after): void
    {
        try {
            AuditLog::create([
                'user_id'    => $actor->id,
                'action'     => $action,
                'table_name' => 'overtime_requests',
                'record_id'  => $recordId,
                'old_data'   => $before,
                'new_data'   => $after,
                'ip_address' => request()->ip(),
            ]);
        } catch (\Throwable) {
            // Non-fatal
        }
    }
}
