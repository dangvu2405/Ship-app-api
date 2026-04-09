<?php

declare(strict_types=1);

namespace App\Http\Requests\Invoice;

use App\Models\Invoice;
use App\Models\Trip;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

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
            'paid_at' => 'nullable|date|after_or_equal:issued_at',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $invoiceId = (int) $this->route('invoice');
            $invoice = Invoice::query()->find($invoiceId);

            if (! $invoice) {
                return;
            }

            $tripId = $this->input('trip_id', $invoice->trip_id);
            $customerId = (int) $this->input('customer_id', $invoice->customer_id);

            if ($tripId) {
                $trip = Trip::query()->find($tripId);
                if ($trip) {
                    if ($trip->status !== 'completed') {
                        $validator->errors()->add('trip_id', 'Chỉ được gắn hóa đơn với chuyến đã completed.');
                    }

                    if ((int) $trip->customer_id !== $customerId) {
                        $validator->errors()->add('customer_id', 'customer_id phải trùng với khách hàng của chuyến đi.');
                    }

                    if (Invoice::query()
                        ->where('id', '!=', $invoiceId)
                        ->where('trip_id', $tripId)
                        ->exists()) {
                        $validator->errors()->add('trip_id', 'Chuyến đi này đã có hóa đơn khác.');
                    }
                }
            }

            $subtotal = (float) $this->input('subtotal', $invoice->subtotal);
            $vatRate = (float) $this->input('vat_rate', $invoice->vat_rate);
            $vatAmount = $this->filled('vat_amount')
                ? (float) $this->input('vat_amount')
                : (float) $invoice->vat_amount;

            if (! $this->filled('vat_amount') && $this->has('subtotal') && $this->has('vat_rate')) {
                $vatAmount = round($subtotal * $vatRate / 100, 2);
            }

            $expectedTotal = round($subtotal + $vatAmount, 2);
            $inputTotal = round((float) $this->input('total_amount', $invoice->total_amount), 2);

            if (abs($inputTotal - $expectedTotal) > 0.01) {
                $validator->errors()->add('total_amount', 'total_amount phải bằng subtotal + vat_amount.');
            }
        });
    }
}
