<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\Payroll;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PayrollExport implements FromCollection, WithHeadings, WithMapping, WithStyles
{
    protected $payroll;

    public function __construct(Payroll $payroll)
    {
        $this->payroll = $payroll;
    }

    public function collection()
    {
        return $this->payroll->details()
            ->with('employee')
            ->orderBy('id')
            ->lazy();
    }

    public function headings(): array
    {
        return [
            'Employee Code',
            'Employee Name',
            'Base Salary',
            'Working Days',
            'Overtime',
            'Bonus',
            'Allowance',
            'Deduction',
            'Fuel Cost',
            'Tax',
            'Net Salary',
        ];
    }

    public function map($payrollDetail): array
    {
        return [
            $payrollDetail->employee->code,
            $payrollDetail->employee->name,
            $payrollDetail->base_salary,
            $payrollDetail->working_days,
            $payrollDetail->overtime,
            $payrollDetail->bonus,
            $payrollDetail->allowance,
            $payrollDetail->deduction,
            $payrollDetail->fuel_cost,
            $payrollDetail->tax,
            $payrollDetail->net_salary,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
