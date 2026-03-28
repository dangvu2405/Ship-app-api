<?php

declare(strict_types=1);

namespace App\Http\Requests\Invoice;

use Illuminate\Foundation\Http\FormRequest;

class UpdateInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('invoice');

        return [
            'code' => 'sometimes|string|max:50|unique:invoices,code,' . $id,
            'trip_id' => 'nullable|exists:trips,id',
            'customer_id' => 'sometimes|exists:customers,id',
            'subtotal' => 'sometimes|numeric|min:0',
            'vat_rate' => 'nullable|numeric|min:0|max:100',
            'vat_amount' => 'nullable|numeric|min:0',
            'total_amount' => 'sometimes|numeric|min:0',
            'status' => 'sometimes|in:draft,issued,paid,cancelled',
            'issued_at' => 'nullable|date',
            'paid_at' => 'nullable|date',
        ];
    }
}
