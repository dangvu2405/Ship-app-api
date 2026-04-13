<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class SocialLoginRequest extends FormRequest
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
            'provider' => ['required', 'string', 'in:google,facebook,apple'],
            'access_token' => ['nullable', 'string', 'required_without:id_token'],
            'id_token' => ['nullable', 'string', 'required_without:access_token'],
        ];
    }
}
