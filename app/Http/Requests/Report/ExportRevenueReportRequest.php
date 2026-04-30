<?php

declare(strict_types=1);

namespace App\Http\Requests\Report;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Tham số giống {@see RevenueSummaryRequest} — kỳ theo from/to hoặc month/year.
 */
class ExportRevenueReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'company_id' => 'nullable|integer|exists:companies,id',
            'month' => 'nullable|integer|min:1|max:12',
            'year' => 'nullable|integer|min:2000|max:2100',
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
        ];
    }

    protected function prepareForValidation(): void
    {
        $month = $this->input('month');
        $year = $this->input('year');
        $from = $this->input('from');
        $to = $this->input('to');

        if ($from === null && $to === null && $month !== null && $year !== null) {
            $periodStart = CarbonImmutable::createFromDate((int) $year, (int) $month, 1)->startOfMonth();
            $periodEnd = $periodStart->endOfMonth();

            $this->merge([
                'from' => $periodStart->toDateString(),
                'to' => $periodEnd->toDateString(),
            ]);
        }
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $hasRange = $this->filled('from') && $this->filled('to');
            $hasMonthYear = $this->filled('month') && $this->filled('year');

            if (! $hasRange && ! $hasMonthYear) {
                $validator->errors()->add(
                    'from',
                    __('api.validation.report_revenue_missing_filters'),
                );
            }
        });
    }
}
