<?php

declare(strict_types=1);

namespace Src\Interface\Http\Requests\Employee;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Src\Application\Employee\DTOs\ListEmployeesCriteria;

final class ListEmployeesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'keyword' => ['nullable', 'string', 'max:100'],
            'q' => ['nullable', 'string', 'max:100'],
            'sort_by' => [
                'sometimes',
                'string',
                Rule::in(['id', 'code', 'name', 'email', 'type', 'status', 'office_id', 'join_date', 'created_at']),
            ],
            'sort_order' => ['sometimes', 'string', Rule::in(['asc', 'desc'])],
            'office_id' => ['nullable', 'integer', Rule::exists('offices', 'id')],
            'department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')],
            'type' => ['nullable', Rule::in(['office', 'driver'])],
            'status' => ['nullable', Rule::in(['active', 'inactive', 'resigned'])],
        ];
    }

    public function toListCriteria(): ListEmployeesCriteria
    {
        return ListEmployeesCriteria::fromArray($this->validated());
    }
}
