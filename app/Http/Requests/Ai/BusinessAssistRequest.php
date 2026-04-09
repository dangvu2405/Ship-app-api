<?php

declare(strict_types=1);

namespace App\Http\Requests\Ai;

use Illuminate\Foundation\Http\FormRequest;

class BusinessAssistRequest extends FormRequest
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
            'task' => 'required|string|in:dashboard_insight,payroll_analysis,trip_optimization,risk_alert,recommendation',
            'company_id' => 'nullable|integer|exists:companies,id',
            'month' => 'nullable|integer|min:1|max:12',
            'year' => 'nullable|integer|min:2000|max:2100',
            'language' => 'nullable|string|in:vi,en',
            'tone' => 'nullable|string|in:concise,detailed,executive',
            'question' => 'nullable|string|max:2000',
            'context' => 'nullable|array',
        ];
    }
}
