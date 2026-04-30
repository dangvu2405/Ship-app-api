<?php

declare(strict_types=1);

namespace App\Http\Requests\Quotation;

use App\Http\Requests\AppFormRequest;

class RejectQuotationRequest extends AppFormRequest
{
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:2000'],
        ];
    }
}

