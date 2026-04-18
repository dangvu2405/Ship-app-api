<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Payroll;
use App\Models\PayrollAdjustment;
use App\Models\User;
use App\Models\Violation;
use App\Models\ViolationDispute;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ViolationService
{
    public function __construct(
        private readonly DriverPayrollCalculationService $payrollCalc,
    ) {}

    /**
     * Create a new violation. Reporter is set to the acting user.
     *
     * @param array<string, mixed> $data
     */
    public function create(array $data, User $actor): Violation
    {
        return DB::transaction(function () use ($data, $actor): Violation {
            $violation = Violation::create([
                ...$data,
                'status'      => 'pending',
                'reported_by' => $actor->id,
            ]);

            $this->auditLog($actor, 'violation.created', $violation->id, null, $violation->toArray());

            return $violation->load(['driver', 'trip', 'reporter']);
        });
    }

    /**
     * Confirm a violation. Enforces SoD: confirmer must not be the reporter.
     */
    public function confirm(Violation $violation, User $actor): Violation
    {
        if ($violation->reported_by === $actor->id) {
            throw new InvalidArgumentException(
                'Separation of Duties: the reporter cannot confirm the same violation.',
                403,
            );
        }

        if ($violation->status !== 'pending') {
            throw new InvalidArgumentException(
                "Cannot confirm violation in status '{$violation->status}'.",
            );
        }

        $before = $violation->toArray();

        DB::transaction(function () use ($violation, $actor, $before): void {
            $violation->update([
                'status'       => 'confirmed',
                'confirmed_by' => $actor->id,
                'confirmed_at' => now(),
            ]);

            $this->auditLog($actor, 'violation.confirmed', $violation->id, $before, $violation->fresh()->toArray());
        });

        return $violation->fresh(['driver', 'trip', 'reporter', 'confirmer']);
    }

    /**
     * Open a dispute on a confirmed or pending violation.
     *
     * @param array<string, mixed> $data
     */
    public function dispute(Violation $violation, array $data, User $actor): ViolationDispute
    {
        if (! in_array($violation->status, ['pending', 'confirmed'])) {
            throw new InvalidArgumentException(
                "Cannot dispute violation in status '{$violation->status}'.",
            );
        }

        if ($violation->dispute()->exists()) {
            throw new InvalidArgumentException('A dispute already exists for this violation.');
        }

        return DB::transaction(function () use ($violation, $data, $actor): ViolationDispute {
            $dispute = $violation->dispute()->create([
                'driver_id'     => $violation->driver_id,
                'reason'        => $data['reason'],
                'evidence_urls' => $data['evidence_urls'] ?? null,
                'status'        => 'open',
            ]);

            $violation->update(['status' => 'disputed']);

            $this->auditLog($actor, 'violation.disputed', $violation->id, null, [
                'dispute_id' => $dispute->id,
                'reason'     => $data['reason'],
            ]);

            return $dispute->load('violation.driver');
        });
    }

    /**
     * Resolve a dispute.
     *
     * @param array<string, mixed> $data  Keys: resolution (upheld|overturned), resolution_note
     */
    public function resolveDispute(ViolationDispute $dispute, array $data, User $actor): ViolationDispute
    {
        if ($dispute->status !== 'open' && $dispute->status !== 'under_review') {
            throw new InvalidArgumentException('Dispute is already resolved.');
        }

        return DB::transaction(function () use ($dispute, $data, $actor): ViolationDispute {
            $resolution    = $data['resolution'] ?? 'upheld';
            $disputeStatus = $resolution === 'overturned' ? 'resolved_overturned' : 'resolved_upheld';

            $dispute->update([
                'status'          => $disputeStatus,
                'resolved_by'     => $actor->id,
                'resolved_at'     => now(),
                'resolution_note' => $data['resolution_note'] ?? null,
            ]);

            $violation          = $dispute->violation;
            $newViolationStatus = $resolution === 'overturned' ? 'waived' : 'confirmed';

            $violation->update([
                'status'       => $newViolationStatus,
                'confirmed_by' => $resolution === 'upheld' ? $actor->id : null,
                'confirmed_at' => $resolution === 'upheld' ? now() : null,
                'waived_by'    => $resolution === 'overturned' ? $actor->id : null,
                'waived_at'    => $resolution === 'overturned' ? now() : null,
            ]);

            $this->auditLog($actor, 'violation.dispute_resolved', $violation->id, null, [
                'resolution'     => $resolution,
                'dispute_status' => $disputeStatus,
            ]);

            // When overturned: remove deduction from draft payrolls, or create a
            // retroactive PayrollAdjustment if the payroll is already locked/paid.
            if ($resolution === 'overturned') {
                $this->handleViolationOverturn($violation, $actor);
            }

            return $dispute->fresh('violation');
        });
    }

    /**
     * Waive a violation without requiring a dispute process.
     */
    public function waive(Violation $violation, string $reason, User $actor): Violation
    {
        if ($violation->status === 'waived') {
            throw new InvalidArgumentException('Violation is already waived.');
        }

        if ($violation->reported_by === $actor->id) {
            throw new InvalidArgumentException(
                'Separation of Duties: the reporter cannot waive the same violation.',
                403,
            );
        }

        DB::transaction(function () use ($violation, $reason, $actor): void {
            $before = $violation->toArray();
            $violation->update([
                'status'       => 'waived',
                'waived_by'    => $actor->id,
                'waived_at'    => now(),
                'waive_reason' => $reason,
            ]);
            $this->auditLog($actor, 'violation.waived', $violation->id, $before, $violation->fresh()->toArray());

            // Remove the deduction from any open draft payroll for this period.
            $this->recalculateDraftForViolation($violation);
        });

        return $violation->fresh(['driver', 'reporter']);
    }

    /**
     * Handle the payroll impact of a violation being overturned.
     *
     * - If the period's payroll is still a draft/approved → recalculate immediately.
     * - If it is locked/paid → create a PayrollAdjustment in the current open payroll
     *   so the driver is compensated in the next pay cycle.
     */
    private function handleViolationOverturn(Violation $violation, User $actor): void
    {
        $occurredAt = Carbon::parse($violation->occurred_at);

        $frozenPayroll = Payroll::query()
            ->where('company_id', $violation->company_id)
            ->where('month', $occurredAt->month)
            ->where('year', $occurredAt->year)
            ->whereIn('status', ['locked', 'paid'])
            ->first();

        if ($frozenPayroll !== null) {
            // Can't touch the locked payroll — schedule a refund in the current period.
            $currentPayroll = Payroll::query()
                ->where('company_id', $violation->company_id)
                ->whereIn('status', ['draft', 'approved'])
                ->orderByDesc('year')
                ->orderByDesc('month')
                ->first();

            if ($currentPayroll !== null && $violation->penalty_amount > 0) {
                PayrollAdjustment::create([
                    'company_id'          => $violation->company_id,
                    'payroll_id'          => $currentPayroll->id,
                    'original_payroll_id' => $frozenPayroll->id,
                    'driver_id'           => $violation->driver_id,
                    'type'                => 'addition',
                    'category'            => 'violation_refund',
                    'amount'              => $violation->penalty_amount,
                    'reason'              => sprintf(
                        'Violation #%d (occurred %s) overturned — refund of penalty VND %s deducted in payroll #%d (%d/%d).',
                        $violation->id,
                        $occurredAt->toDateString(),
                        number_format($violation->penalty_amount),
                        $frozenPayroll->id,
                        $frozenPayroll->month,
                        $frozenPayroll->year,
                    ),
                    'source_type' => 'violation',
                    'source_id'   => $violation->id,
                    'approved_by' => $actor->id,
                ]);
            }

            return;
        }

        // Payroll is still open — recalculate so the deduction disappears immediately.
        $this->recalculateDraftForViolation($violation);
    }

    /**
     * If there is an unlocked (draft/approved) payroll for the month of the
     * violation, recalculate it so the deduction change is reflected immediately.
     * Locked payrolls are intentionally left untouched.
     */
    private function recalculateDraftForViolation(Violation $violation): void
    {
        $occurredAt = Carbon::parse($violation->occurred_at);

        $payroll = Payroll::query()
            ->where('company_id', $violation->company_id)
            ->where('month', $occurredAt->month)
            ->where('year', $occurredAt->year)
            ->whereIn('status', ['draft', 'approved'])
            ->first();

        if ($payroll === null) {
            return;
        }

        $this->payrollCalc->createOrRecalculateDraft(
            $violation->company_id,
            $occurredAt->month,
            $occurredAt->year,
        );
    }

    /**
     * Sum confirmed penalty amounts for a driver in a payroll period.
     */
    public function confirmedPenaltyForPeriod(int $driverId, string $from, string $to): float
    {
        return (float) Violation::query()
            ->where('driver_id', $driverId)
            ->where('status', 'confirmed')
            ->whereBetween('occurred_at', [$from.' 00:00:00', $to.' 23:59:59'])
            ->sum('penalty_amount');
    }

    /** @param array<string, mixed>|null $before @param array<string, mixed> $after */
    private function auditLog(User $actor, string $action, int $recordId, ?array $before, array $after): void
    {
        try {
            AuditLog::create([
                'user_id'    => $actor->id,
                'action'     => $action,
                'table_name' => 'violations',
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
