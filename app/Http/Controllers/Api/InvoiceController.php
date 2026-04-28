<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\Invoice\StoreInvoiceRequest;
use App\Http\Requests\Invoice\UpdateInvoiceRequest;
use App\Http\Traits\HasIndexQuery;
use App\Models\Invoice;
use App\Services\InvoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * @OA\Tag(name="Invoices", description="Quản lý hóa đơn")
 */
class InvoiceController extends BaseController
{
    use HasIndexQuery;

    protected array $allowedSortColumns = ['id', 'code', 'customer_id', 'trip_id', 'status', 'total_amount', 'issued_at', 'created_at'];

    public function __construct(private readonly InvoiceService $invoiceService) {}

    /**
     * @OA\Get(
     *     path="/api/invoices",
     *     tags={"Invoices"},
     *     summary="Danh sách hóa đơn",
     *     @OA\Parameter(name="search", in="query", description="Tìm theo code", @OA\Schema(type="string")),
     *     @OA\Parameter(name="trip_id", in="query", description="Lọc theo chuyến xe", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="customer_id", in="query", description="Lọc theo khách hàng", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="status", in="query", description="Lọc theo trạng thái", @OA\Schema(type="string")),
     *     @OA\Parameter(name="sort", in="query", description="Sắp xếp", @OA\Schema(type="string")),
     *     @OA\Parameter(name="per_page", in="query", description="Số bản ghi/trang", @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Thành công")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $query = Invoice::query()->with(['trip', 'customer']);
        $result = $this->indexQuery($request, $query, ['code'], [
            'trip_id' => 'trip_id',
            'customer_id' => 'customer_id',
            'status' => 'status',
        ]);

        return $this->successResponse($result, 'api.common.ok');
    }

    /**
     * @OA\Post(
     *     path="/api/invoices",
     *     tags={"Invoices"},
     *     summary="Tạo hóa đơn mới",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"code","customer_id","total_amount"},
     *             @OA\Property(property="code", type="string", example="INV001"),
     *             @OA\Property(property="customer_id", type="integer", example=1),
     *             @OA\Property(property="trip_id", type="integer"),
     *             @OA\Property(property="total_amount", type="number", example=5000000),
     *             @OA\Property(property="tax_amount", type="number"),
     *             @OA\Property(property="issued_at", type="string", format="date"),
     *             @OA\Property(property="due_date", type="string", format="date"),
     *             @OA\Property(property="status", type="string", enum={"draft","sent","paid","cancelled"})
     *         )
     *     ),
     *     @OA\Response(response=201, description="Tạo thành công"),
     *     @OA\Response(response=422, description="Validation lỗi")
     * )
     */
    public function store(StoreInvoiceRequest $request): JsonResponse
    {
        $invoice = Invoice::create($request->validated());

        return $this->successResponse($invoice->load(['trip', 'customer']), 'api.invoice.created', 201);
    }

    /**
     * @OA\Get(
     *     path="/api/invoices/{id}",
     *     tags={"Invoices"},
     *     summary="Chi tiết hóa đơn",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Thành công"),
     *     @OA\Response(response=404, description="Không tìm thấy")
     * )
     */
    public function show(string $invoice): JsonResponse
    {
        $model = Invoice::with(['trip', 'customer'])->find($invoice);
        if (! $model) {
            return $this->notFoundResponse('api.invoice.not_found');
        }

        return $this->successResponse($model, 'api.common.ok');
    }

    /**
     * @OA\Put(
     *     path="/api/invoices/{id}",
     *     tags={"Invoices"},
     *     summary="Cập nhật hóa đơn",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="code", type="string"),
     *             @OA\Property(property="customer_id", type="integer"),
     *             @OA\Property(property="trip_id", type="integer"),
     *             @OA\Property(property="total_amount", type="number"),
     *             @OA\Property(property="tax_amount", type="number"),
     *             @OA\Property(property="issued_at", type="string", format="date"),
     *             @OA\Property(property="due_date", type="string", format="date"),
     *             @OA\Property(property="status", type="string")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Cập nhật thành công"),
     *     @OA\Response(response=404, description="Không tìm thấy"),
     *     @OA\Response(response=422, description="Validation lỗi")
     * )
     */
    public function update(UpdateInvoiceRequest $request, string $invoice): JsonResponse
    {
        $model = Invoice::find($invoice);
        if (! $model) {
            return $this->notFoundResponse('api.invoice.not_found');
        }
        $model->update($request->validated());

        return $this->successResponse($model->fresh(['trip', 'customer']), 'api.invoice.updated');
    }

    /**
     * @OA\Delete(
     *     path="/api/invoices/{id}",
     *     tags={"Invoices"},
     *     summary="Xóa hóa đơn",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Xóa thành công"),
     *     @OA\Response(response=404, description="Không tìm thấy")
     * )
     */
    public function destroy(string $invoice): JsonResponse
    {
        $model = Invoice::find($invoice);
        if (! $model) {
            return $this->notFoundResponse('api.invoice.not_found');
        }
        $model->delete();

        return $this->successResponse(null, 'api.invoice.deleted');
    }

    public function issue(string $invoice): JsonResponse
    {
        $model = Invoice::find($invoice);
        if (! $model) {
            return $this->notFoundResponse('api.invoice.not_found');
        }

        try {
            return $this->successResponse($this->invoiceService->issue($model), 'api.invoice.issued');
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    public function markPaid(string $invoice): JsonResponse
    {
        $model = Invoice::find($invoice);
        if (! $model) {
            return $this->notFoundResponse('api.invoice.not_found');
        }

        try {
            return $this->successResponse($this->invoiceService->markPaid($model), 'api.invoice.marked_paid');
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    public function sendCqt(string $invoice): JsonResponse
    {
        $model = Invoice::find($invoice);
        if (! $model) {
            return $this->notFoundResponse('api.invoice.not_found');
        }

        try {
            return $this->successResponse($this->invoiceService->sendCqt($model), 'api.invoice.submitted_tax_authority');
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    public function cancel(string $invoice): JsonResponse
    {
        $model = Invoice::find($invoice);
        if (! $model) {
            return $this->notFoundResponse('api.invoice.not_found');
        }

        try {
            return $this->successResponse($this->invoiceService->cancel($model), 'api.invoice.cancelled');
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }
}
