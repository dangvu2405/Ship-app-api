<?php

declare(strict_types=1);

namespace App\Http\Requests\Invoice;

use App\Http\Requests\AppFormRequest;
use App\Models\Invoice;
use App\Models\Trip;
use Illuminate\Validation\Validator;

class UpdateInvoiceRequest extends AppFormRequest
{
    public function authorize(): bool
    {
        return $this->authorizePermission('accounting', 'edit');
    }

    public function rules(): array
    {
        $id = $this->route('invoice');

        return [
            'code' => 'sometimes|string|max:50|unique:invoices,code,'.$id,
            'trip_id' => ['nullable', 'integer', $this->existsInCompany('trips')],
            'customer_id' => ['sometimes', 'integer', $this->existsInCompany('customers')],
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
            $companyId = $this->tenantCompanyId();
            $invoiceId = (int) $this->route('invoice');
            $invoice = Invoice::query()
                ->when($companyId !== null, fn ($query) => $query->where('company_id', $companyId))
                ->find($invoiceId);

            if (! $invoice) {
                return;
            }

            $tripId = $this->input('trip_id', $invoice->trip_id);
            $customerId = (int) $this->input('customer_id', $invoice->customer_id);

            if ($tripId) {
                $trip = Trip::query()
                    ->when($companyId !== null, fn ($query) => $query->where('company_id', $companyId))
                    ->find($tripId);
                if ($trip) {
                    if ($trip->status !== 'completed') {
                        $validator->errors()->add('trip_id', 'Chỉ được gắn hóa đơn với chuyến đã completed.');
                    }

                    if ((int) $trip->customer_id !== $customerId) {
                        $validator->errors()->add('customer_id', 'customer_id phải trùng với khách hàng của chuyến đi.');
                    }

                    if (Invoice::query()
                        ->when($companyId !== null, fn ($query) => $query->where('company_id', $companyId))
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
