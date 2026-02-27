<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Invoice\StoreInvoiceRequest;
use App\Http\Requests\Invoice\UpdateInvoiceRequest;
use App\Http\Traits\HasIndexQuery;
use App\Models\Invoice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvoiceController extends BaseController
{
    use HasIndexQuery;

    protected array $allowedSortColumns = ['id', 'code', 'customer_id', 'trip_id', 'status', 'total_amount', 'issued_at', 'created_at'];

    public function index(Request $request): JsonResponse
    {
        $query = Invoice::query()->with(['trip', 'customer']);
        $result = $this->indexQuery($request, $query, ['code'], [
            'trip_id' => 'trip_id',
            'customer_id' => 'customer_id',
            'status' => 'status',
        ]);

        return $this->successResponse($result, 'OK');
    }

    public function store(StoreInvoiceRequest $request): JsonResponse
    {
        $invoice = Invoice::create($request->validated());

        return $this->successResponse($invoice->load(['trip', 'customer']), 'Invoice created successfully', 201);
    }

    public function show(string $invoice): JsonResponse
    {
        $model = Invoice::with(['trip', 'customer'])->find($invoice);
        if (! $model) {
            return $this->notFoundResponse('Invoice not found');
        }

        return $this->successResponse($model);
    }

    public function update(UpdateInvoiceRequest $request, string $invoice): JsonResponse
    {
        $model = Invoice::find($invoice);
        if (! $model) {
            return $this->notFoundResponse('Invoice not found');
        }
        $model->update($request->validated());

        return $this->successResponse($model->fresh(['trip', 'customer']), 'Invoice updated successfully');
    }

    public function destroy(string $invoice): JsonResponse
    {
        $model = Invoice::find($invoice);
        if (! $model) {
            return $this->notFoundResponse('Invoice not found');
        }
        $model->delete();

        return $this->successResponse(null, 'Invoice deleted successfully');
    }
}
