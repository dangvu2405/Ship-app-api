<?php

declare(strict_types=1);

namespace App\Http\Requests\Report;

use App\Http\Requests\AppFormRequest;

class DashboardRequest extends AppFormRequest
{
    public function authorize(): bool
    {
        return $this->authorizePermission('reports', 'view');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'month' => 'nullable|integer|min:1|max:12',
            'year' => 'nullable|integer|min:2000|max:2100',
        ];
    }
}
