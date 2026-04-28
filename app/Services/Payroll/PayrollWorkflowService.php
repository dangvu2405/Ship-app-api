<?php

declare(strict_types=1);

namespace App\Services\Payroll;

use App\Models\Payroll;
use App\Services\DriverPayrollCalculationService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PayrollWorkflowService
{
    public function __construct(private readonly DriverPayrollCalculationService $calculationService) {}

    /**
     * @param array<string, mixed> $payload
     */
    public function update(Payroll $payroll, array $payload): Payroll
    {
        if ($payroll->isFrozen()) {
            throw new InvalidArgumentException('Payroll is locked/paid and cannot be updated.');
        }

        $payroll->fill($payload);
        $payroll->save();

        return $payroll->fresh(['company', 'lines.driver']);
    }

    public function delete(Payroll $payroll): void
    {
        if ($payroll->isFrozen()) {
            throw new InvalidArgumentException('Payroll is locked/paid and cannot be deleted.');
        }

        DB::transaction(function () use ($payroll): void {
            $payroll->lines()->delete();
            $payroll->delete();
        });
    }

    public function approve(int $payrollId): Payroll
    {
        $payroll = Payroll::query()->findOrFail($payrollId);

        if (! $payroll->isLocked()) {
            throw new InvalidArgumentException(
                "Payroll must be in 'locked' status before approving (current: {$payroll->status}).",
            );
        }

        $payroll->update([
            'status'      => 'approved',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        return $payroll->fresh(['company', 'lines.driver']);
    }

    public function lock(int $payrollId): Payroll
    {
        $payroll = Payroll::query()->findOrFail($payrollId);

        return $this->calculationService->lock($payroll);
    }

    /**
     * Mark a locked payroll as paid (salary has been disbursed).
     * Transition: locked → paid
     */
    public function markPaid(int $payrollId, int $actorId): Payroll
    {
        $payroll = Payroll::query()->findOrFail($payrollId);

        if (! $payroll->isApproved()) {
            throw new InvalidArgumentException(
                "Payroll must be in 'approved' status before marking as paid (current: {$payroll->status}).",
            );
        }

        $payroll->update([
            'status'  => 'paid',
            'paid_at' => now(),
            'paid_by' => $actorId,
        ]);

        return $payroll->fresh(['company', 'lines.driver']);
    }
}
