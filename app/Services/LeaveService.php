<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditLog;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class LeaveService
{
    /**
     * Submit a leave request for a driver.
     *
     * @param array<string, mixed> $data
     */
    public function create(array $data, User $actor): LeaveRequest
    {
        $driverId    = (int) $data['driver_id'];
        $leaveTypeId = (int) $data['leave_type_id'];
        $from        = $data['from_date'];
        $to          = $data['to_date'];
        $totalDays   = (float) $data['total_days'];

        // Check for overlapping approved/pending requests
        $overlap = LeaveRequest::query()
            ->where('driver_id', $driverId)
            ->scopeOverlapping($from, $to)
            ->exists();

        if ($overlap) {
            throw new InvalidArgumentException(
                'Driver already has a leave request that overlaps with the requested dates.',
            );
        }

        // Check leave balance
        $leaveType = LeaveType::findOrFail($leaveTypeId);
        if ($leaveType->is_paid) {
            $year    = (int) substr($from, 0, 4);
            $balance = LeaveBalance::query()
                ->where('driver_id', $driverId)
                ->where('leave_type_id', $leaveTypeId)
                ->where('year', $year)
                ->first();

            if ($balance && $balance->remainingDays() < $totalDays) {
                throw new InvalidArgumentException(
                    sprintf(
                        'Insufficient leave balance. Remaining: %.1f days, Requested: %.1f days.',
                        $balance->remainingDays(),
                        $totalDays,
                    ),
                );
            }
        }

        return DB::transaction(function () use ($data, $actor): LeaveRequest {
            $request = LeaveRequest::create([
                ...$data,
                'status'     => 'pending',
                'created_by' => $actor->id,
            ]);

            $this->auditLog($actor, 'leave.created', $request->id, null, $request->toArray());

            return $request->load(['driver', 'leaveType']);
        });
    }

    /**
     * Approve a leave request. SoD: approver must not be the creator.
     */
    public function approve(LeaveRequest $request, User $actor): LeaveRequest
    {
        if ($request->created_by === $actor->id) {
            throw new InvalidArgumentException(
                'Separation of Duties: the creator cannot approve their own leave request.',
                403,
            );
        }

        if ($request->status !== 'pending') {
            throw new InvalidArgumentException("Cannot approve request in status '{$request->status}'.");
        }

        return DB::transaction(function () use ($request, $actor): LeaveRequest {
            $before = $request->toArray();

            $request->update([
                'status'      => 'approved',
                'approved_by' => $actor->id,
                'approved_at' => now(),
            ]);

            // Deduct from balance for paid leave
            $leaveType = $request->leaveType;
            if ($leaveType && $leaveType->is_paid) {
                $year = (int) $request->from_date->year;
                LeaveBalance::query()
                    ->where('driver_id', $request->driver_id)
                    ->where('leave_type_id', $request->leave_type_id)
                    ->where('year', $year)
                    ->increment('used_days', (float) $request->total_days);
            }

            $this->auditLog($actor, 'leave.approved', $request->id, $before, $request->fresh()->toArray());

            return $request->fresh(['driver', 'leaveType', 'approver']);
        });
    }

    /**
     * Reject a leave request.
     */
    public function reject(LeaveRequest $request, string $reason, User $actor): LeaveRequest
    {
        if ($request->status !== 'pending') {
            throw new InvalidArgumentException("Cannot reject request in status '{$request->status}'.");
        }

        $before = $request->toArray();
        $request->update([
            'status'           => 'rejected',
            'rejection_reason' => $reason,
        ]);
        $this->auditLog($actor, 'leave.rejected', $request->id, $before, $request->fresh()->toArray());

        return $request->fresh(['driver', 'leaveType']);
    }

    /**
     * Cancel a leave request (by driver or admin before payroll lock).
     */
    public function cancel(LeaveRequest $request, User $actor): LeaveRequest
    {
        if (! in_array($request->status, ['pending', 'approved'])) {
            throw new InvalidArgumentException("Cannot cancel request in status '{$request->status}'.");
        }

        return DB::transaction(function () use ($request, $actor): LeaveRequest {
            $before = $request->toArray();

            // Restore balance if was approved paid leave
            if ($request->status === 'approved') {
                $leaveType = $request->leaveType;
                if ($leaveType && $leaveType->is_paid) {
                    $year = (int) $request->from_date->year;
                    LeaveBalance::query()
                        ->where('driver_id', $request->driver_id)
                        ->where('leave_type_id', $request->leave_type_id)
                        ->where('year', $year)
                        ->decrement('used_days', (float) $request->total_days);
                }
            }

            $request->update(['status' => 'cancelled']);
            $this->auditLog($actor, 'leave.cancelled', $request->id, $before, $request->fresh()->toArray());

            return $request->fresh(['driver', 'leaveType']);
        });
    }

    /**
     * Get total unpaid leave days for a driver in a period (for payroll proration).
     */
    public function unpaidLeaveDaysForPeriod(int $driverId, string $from, string $to): float
    {
        return (float) LeaveRequest::query()
            ->where('driver_id', $driverId)
            ->where('status', 'approved')
            ->whereHas('leaveType', fn ($q) => $q->where('is_paid', false))
            ->where('from_date', '<=', $to)
            ->where('to_date', '>=', $from)
            ->sum('total_days');
    }

    /** @param array<string, mixed>|null $before @param array<string, mixed> $after */
    private function auditLog(User $actor, string $action, int $recordId, ?array $before, array $after): void
    {
        try {
            AuditLog::create([
                'user_id'    => $actor->id,
                'action'     => $action,
                'table_name' => 'leave_requests',
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
