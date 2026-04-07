<?php

declare(strict_types=1);

namespace App\Http\Requests\Example;

use Illuminate\Foundation\Http\FormRequest;

class UpdateExampleUserRequest extends FormRequest
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
        $id = (int) $this->route('id');

        return [
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $id,
        ];
    }
}
