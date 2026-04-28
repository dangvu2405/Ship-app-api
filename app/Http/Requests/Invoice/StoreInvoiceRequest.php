<?php

declare(strict_types=1);

namespace App\Http\Requests\Invoice;

use App\Models\Invoice;
use App\Models\Trip;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => 'required|string|max:50|unique:invoices,code',
            'trip_id' => 'nullable|exists:trips,id',
            'customer_id' => 'required|exists:customers,id',
            'subtotal' => 'required|numeric|min:0',
            'vat_rate' => 'nullable|numeric|min:0|max:100',
            'vat_amount' => 'nullable|numeric|min:0',
            'total_amount' => 'required|numeric|min:0',
            'status' => 'required|in:draft,issued,paid,cancelled',
            'issued_at' => 'nullable|date',
            'paid_at' => 'nullable|date|after_or_equal:issued_at',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $tripId = $this->input('trip_id');
            $customerId = (int) $this->input('customer_id');

            if ($tripId) {
                $trip = Trip::query()->find($tripId);

                if (! $trip) {
                    return;
                }

                if ($trip->status !== 'completed') {
                    $validator->errors()->add('trip_id', __('api.validation.invoice_trip_must_be_completed'));
                }

                if ((int) $trip->customer_id !== $customerId) {
                    $validator->errors()->add('customer_id', __('api.validation.invoice_customer_mismatch'));
                }

                if (Invoice::query()->where('trip_id', $tripId)->exists()) {
                    $validator->errors()->add('trip_id', __('api.validation.invoice_trip_already_has_invoice'));
                }
            }

            $subtotal = (float) $this->input('subtotal', 0);
            $vatRate = (float) $this->input('vat_rate', 0);
            $vatAmount = $this->filled('vat_amount')
                ? (float) $this->input('vat_amount')
                : round($subtotal * $vatRate / 100, 2);
            $expectedTotal = round($subtotal + $vatAmount, 2);
            $inputTotal = round((float) $this->input('total_amount', 0), 2);

            if (abs($inputTotal - $expectedTotal) > 0.01) {
                $validator->errors()->add('total_amount', __('api.validation.invoice_total_mismatch'));
            }
        });
    }
}
