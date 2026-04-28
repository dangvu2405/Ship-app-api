<?php

declare(strict_types=1);

namespace App\Services\Payroll;

use App\Models\Payroll;
use App\Models\User;

class PayrollQueryService
{
    public function findByIdForExport(int $id): ?Payroll
    {
        return Payroll::query()
            ->with(['company', 'lines.driver'])
            ->find($id);
    }

    /**
     * @return array<string, mixed>
     */
    public function buildExportPayload(Payroll $payroll): array
    {
        $payroll->loadMissing(['company', 'lines.driver']);

        return [
            'payroll' => $payroll,
            'summary' => [
                'employees_count' => $payroll->lines->pluck('driver_id')->filter()->unique()->count(),
                'total_net_salary' => $payroll->lines->sum('net_salary'),
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findMySalary(User $user, int $month, int $year): ?array
    {
        $driverId = $user->driver?->id;
        if ($driverId === null) {
            return null;
        }

        $line = Payroll::query()
            ->where('month', $month)
            ->where('year', $year)
            ->with(['lines' => function ($query) use ($driverId): void {
                $query->where('driver_id', $driverId)->with('driver');
            }])
            ->first();

        if (! $line || $line->lines->isEmpty()) {
            return null;
        }

        return [
            'payroll' => $line,
            'line' => $line->lines->first(),
        ];
    }
}
