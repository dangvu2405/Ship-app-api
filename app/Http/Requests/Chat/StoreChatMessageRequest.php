<?php

declare(strict_types=1);

namespace App\Http\Requests\Chat;

use Illuminate\Foundation\Http\FormRequest;

class StoreChatMessageRequest extends FormRequest
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
            'message' => 'required|string|max:4000',
            'session_id' => 'nullable|string|max:64',
            'task' => 'nullable|string|in:chat,classify,extract,advice',
            'context' => 'nullable|array',
            'model' => 'nullable|string|max:100',
        ];
    }
}
