<?php

declare(strict_types=1);

namespace App\Services\Payroll;

use App\Models\Payroll;
use App\Models\PayrollAdjustment;
use App\Models\User;
use InvalidArgumentException;

class PayrollAdjustmentService
{
    public function create(array $data): PayrollAdjustment
    {
        $data['category'] ??= 'manual';

        /** @var Payroll $payroll */
        $payroll = Payroll::findOrFail($data['payroll_id']);
        $data['company_id'] = $payroll->company_id;

        $adjustment = PayrollAdjustment::create($data);

        return $adjustment->load(['driver', 'payroll']);
    }

    public function update(PayrollAdjustment $adjustment, array $data): PayrollAdjustment
    {
        if ($adjustment->approved_by !== null) {
            throw new InvalidArgumentException('Approved adjustments cannot be modified');
        }

        $adjustment->update($data);

        return $adjustment->fresh(['driver', 'payroll']);
    }

    public function delete(PayrollAdjustment $adjustment): void
    {
        if ($adjustment->approved_by !== null) {
            throw new InvalidArgumentException('Approved adjustments cannot be deleted');
        }

        $adjustment->delete();
    }

    public function approve(PayrollAdjustment $adjustment, User $approver): PayrollAdjustment
    {
        if ($adjustment->approved_by !== null) {
            throw new InvalidArgumentException('Adjustment already approved');
        }

        $adjustment->update(['approved_by' => $approver->id]);

        return $adjustment->fresh(['driver', 'payroll', 'approver']);
    }
}
