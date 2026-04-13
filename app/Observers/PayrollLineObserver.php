<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Payroll;
use App\Models\PayrollLine;
use Illuminate\Validation\ValidationException;

final class PayrollLineObserver
{
    public function updating(PayrollLine $line): void
    {
        $this->assertPayrollUnlocked($line);
    }

    public function deleting(PayrollLine $line): void
    {
        $this->assertPayrollUnlocked($line);
    }

    private function assertPayrollUnlocked(PayrollLine $line): void
    {
        $locked = Payroll::query()
            ->whereKey($line->payroll_id)
            ->where('status', 'locked')
            ->exists();

        if ($locked) {
            throw ValidationException::withMessages([
                'payroll_line' => ['Payroll is locked; lines cannot be changed.'],
            ]);
        }
    }
}
