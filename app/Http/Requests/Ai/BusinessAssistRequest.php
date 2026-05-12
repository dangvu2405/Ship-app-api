<?php

declare(strict_types=1);

namespace App\Http\Requests\Ai;

use App\Http\Requests\AppFormRequest;
use Illuminate\Validation\Rule;

class BusinessAssistRequest extends AppFormRequest
{
    public function authorize(): bool
    {
        return $this->authorizePermission('reports', 'view');
    }

    public function prepareForValidation(): void
    {
        $tenantId = $this->tenantCompanyId();
        if ($tenantId !== null && $tenantId > 0) {
            $this->merge(['company_id' => $tenantId]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tenantId = $this->tenantCompanyId();

        return [
            'task' => 'required|string|in:dashboard_insight,payroll_analysis,trip_optimization,risk_alert,recommendation',
            'company_id' => array_values(array_filter([
                'nullable',
                'integer',
                Rule::exists('companies', 'id'),
                $tenantId !== null ? Rule::in([$tenantId]) : null,
            ])),
            'month' => 'nullable|integer|min:1|max:12',
            'year' => 'nullable|integer|min:2000|max:2100',
            'language' => 'nullable|string|in:vi,en',
            'tone' => 'nullable|string|in:concise,detailed,executive',
            'question' => 'nullable|string|max:2000',
            'context' => 'nullable|array',
        ];
    }
}
