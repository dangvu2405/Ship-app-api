<?php

declare(strict_types=1);

namespace App\Http\Requests\Lark;

use Illuminate\Foundation\Http\FormRequest;

class LarkWebhookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['nullable', 'string'],
            'token' => ['nullable', 'string'],
            'challenge' => ['nullable', 'string'],
            'event' => ['nullable', 'array'],
            'header' => ['nullable', 'array'],
        ];
    }
}
