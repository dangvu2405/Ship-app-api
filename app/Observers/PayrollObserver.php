<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Payroll;
use Illuminate\Validation\ValidationException;

final class PayrollObserver
{
    public function updating(Payroll $payroll): void
    {
        if ((string) $payroll->getOriginal('status') !== 'locked') {
            return;
        }

        foreach (array_keys($payroll->getDirty()) as $attribute) {
            if (in_array($attribute, ['updated_at'], true)) {
                continue;
            }

            throw ValidationException::withMessages([
                'payroll' => ['Payroll is locked and cannot be modified.'],
            ]);
        }
    }

    public function deleting(Payroll $payroll): void
    {
        if ($payroll->isLocked()) {
            throw ValidationException::withMessages([
                'payroll' => ['Payroll is locked and cannot be deleted.'],
            ]);
        }
    }
}
