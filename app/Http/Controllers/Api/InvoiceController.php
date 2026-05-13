<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\Invoice\StoreInvoiceRequest;
use App\Http\Requests\Invoice\UpdateInvoiceRequest;
use App\Http\Traits\HasIndexQuery;
use App\Models\Invoice;
use App\Models\InvoiceStatusHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * @OA\Tag(name="Invoices", description="Quản lý hóa đơn")
 */
class InvoiceController extends BaseController
{
    use HasIndexQuery;

    protected array $allowedSortColumns = ['id', 'code', 'customer_id', 'trip_id', 'status', 'total_amount', 'issued_at', 'created_at'];

    /**
     * @OA\Get(
     *     path="/api/invoices",
     *     tags={"Invoices"},
     *     summary="Danh sách hóa đơn",
     *
     *     @OA\Parameter(name="search", in="query", description="Tìm theo code", @OA\Schema(type="string")),
     *     @OA\Parameter(name="trip_id", in="query", description="Lọc theo chuyến xe", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="customer_id", in="query", description="Lọc theo khách hàng", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="status", in="query", description="Lọc theo trạng thái", @OA\Schema(type="string")),
     *     @OA\Parameter(name="sort", in="query", description="Sắp xếp", @OA\Schema(type="string")),
     *     @OA\Parameter(name="per_page", in="query", description="Số bản ghi/trang", @OA\Schema(type="integer")),
     *
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

        return $this->successResponse($result, 'OK');
    }

    /**
     * @OA\Post(
     *     path="/api/invoices",
     *     tags={"Invoices"},
     *     summary="Tạo hóa đơn mới",
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"code","customer_id","total_amount"},
     *
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
     *
     *     @OA\Response(response=201, description="Tạo thành công"),
     *     @OA\Response(response=422, description="Validation lỗi")
     * )
     */
    public function store(StoreInvoiceRequest $request): JsonResponse
    {
        $invoice = Invoice::create($request->validated());

        return $this->successResponse($invoice->load(['trip', 'customer']), 'Invoice created successfully', 201);
    }

    /**
     * @OA\Get(
     *     path="/api/invoices/{id}",
     *     tags={"Invoices"},
     *     summary="Chi tiết hóa đơn",
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(response=200, description="Thành công"),
     *     @OA\Response(response=404, description="Không tìm thấy")
     * )
     */
    public function show(string $invoice): JsonResponse
    {
        $model = Invoice::with(['trip', 'customer'])->find($invoice);
        if (! $model) {
            return $this->notFoundResponse('Invoice not found');
        }

        return $this->successResponse($model);
    }

    /**
     * @OA\Put(
     *     path="/api/invoices/{id}",
     *     tags={"Invoices"},
     *     summary="Cập nhật hóa đơn",
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *
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
     *
     *     @OA\Response(response=200, description="Cập nhật thành công"),
     *     @OA\Response(response=404, description="Không tìm thấy"),
     *     @OA\Response(response=422, description="Validation lỗi")
     * )
     */
    public function update(UpdateInvoiceRequest $request, string $invoice): JsonResponse
    {
        $model = Invoice::find($invoice);
        if (! $model) {
            return $this->notFoundResponse('Invoice not found');
        }
        $model->update($request->validated());

        return $this->successResponse($model->fresh(['trip', 'customer']), 'Invoice updated successfully');
    }

    /**
     * @OA\Delete(
     *     path="/api/invoices/{id}",
     *     tags={"Invoices"},
     *     summary="Xóa hóa đơn",
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(response=200, description="Xóa thành công"),
     *     @OA\Response(response=404, description="Không tìm thấy")
     * )
     */
    public function destroy(string $invoice): JsonResponse
    {
        $model = Invoice::find($invoice);
        if (! $model) {
            return $this->notFoundResponse('Invoice not found');
        }
        $model->delete();

        return $this->successResponse(null, 'Invoice deleted successfully');
    }

    public function statusHistories(Request $request, string $invoice): JsonResponse
    {
        $model = Invoice::query()->find($invoice);
        if (! $model) {
            return $this->notFoundResponse('Invoice not found');
        }

        $rows = InvoiceStatusHistory::query()
            ->where('invoice_id', $model->id)
            ->latest('changed_at')
            ->paginate(min(max($request->integer('per_page', 15), 1), 100));

        return $this->successResponse($rows, 'OK');
    }

    public function issue(Request $request, string $invoice): JsonResponse
    {
        $model = Invoice::query()->find($invoice);
        if (! $model) {
            return $this->notFoundResponse('Invoice not found');
        }

        if ($model->status === 'cancelled') {
            return $this->validationErrorResponse(['status' => ['Không thể phát hành hóa đơn đã hủy.']]);
        }

        return $this->transition($request, $model, 'issued', [
            'issued_at' => $model->issued_at ?? now(),
        ], 'Phát hành hóa đơn');
    }

    public function markPaid(Request $request, string $invoice): JsonResponse
    {
        $model = Invoice::query()->find($invoice);
        if (! $model) {
            return $this->notFoundResponse('Invoice not found');
        }

        if ($model->status === 'cancelled') {
            return $this->validationErrorResponse(['status' => ['Không thể thanh toán hóa đơn đã hủy.']]);
        }

        return $this->transition($request, $model, 'paid', [
            'paid_at' => now(),
            'issued_at' => $model->issued_at ?? now(),
        ], 'Ghi nhận thanh toán hóa đơn');
    }

    public function cancel(Request $request, string $invoice): JsonResponse
    {
        $validated = $request->validate([
            'cancel_reason' => ['required', 'string', 'max:500'],
        ]);

        $model = Invoice::query()->find($invoice);
        if (! $model) {
            return $this->notFoundResponse('Invoice not found');
        }

        if ($model->status === 'paid') {
            return $this->validationErrorResponse(['status' => ['Không thể hủy hóa đơn đã thanh toán.']]);
        }

        return $this->transition($request, $model, 'cancelled', [], $validated['cancel_reason']);
    }

    public function pdf(string $invoice): Response|JsonResponse
    {
        $model = Invoice::query()->with(['customer', 'trip'])->find($invoice);
        if (! $model) {
            return $this->notFoundResponse('Invoice not found');
        }

        $content = $this->buildMinimalPdf($model);
        $filename = 'invoice-'.$model->code.'.pdf';

        return response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    public function email(Request $request, string $invoice): JsonResponse
    {
        $model = Invoice::query()->with(['customer', 'trip'])->find($invoice);
        if (! $model) {
            return $this->notFoundResponse('Invoice not found');
        }

        $recipient = $request->input('email') ?: $model->customer?->email;
        if (! is_string($recipient) || ! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            return $this->validationErrorResponse(['email' => ['Khách hàng chưa có email hợp lệ.']]);
        }

        $pdf = $this->buildMinimalPdf($model);
        Mail::raw("Kính gửi Quý khách,\n\nVui lòng xem hóa đơn {$model->code} trong file đính kèm.\n\nTrân trọng.", function ($message) use ($model, $recipient, $pdf): void {
            $message->to($recipient)
                ->subject('Hóa đơn '.$model->code)
                ->attachData($pdf, 'invoice-'.$model->code.'.pdf', ['mime' => 'application/pdf']);
        });

        $this->recordHistory(request: $request, invoice: $model, toStatus: $model->status, note: 'Gửi email hóa đơn tới '.$recipient);

        return $this->successResponse($model->refresh()->load(['trip', 'customer']), 'OK');
    }

    public function cqt(string $invoice): JsonResponse
    {
        $model = Invoice::query()->find($invoice);
        if (! $model) {
            return $this->notFoundResponse('Invoice not found');
        }

        return $this->errorResponse('Chưa cấu hình nhà cung cấp hóa đơn điện tử/CQT.', 422, [
            'cqt_provider' => ['Cần cấu hình provider thật trước khi gửi CQT.'],
        ]);
    }

    /**
     * Keep invoice state changes and their timeline in one transaction.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function transition(Request $request, Invoice $invoice, string $status, array $attributes, string $note): JsonResponse
    {
        $oldStatus = $invoice->status;

        DB::transaction(function () use ($request, $invoice, $status, $attributes, $oldStatus, $note): void {
            $invoice->update(array_merge($attributes, ['status' => $status]));

            $this->recordHistory($request, $invoice, $status, $note, $oldStatus);
        });

        return $this->successResponse($invoice->refresh()->load(['trip', 'customer']), 'OK');
    }

    private function recordHistory(Request $request, Invoice $invoice, string $toStatus, string $note, ?string $fromStatus = null): void
    {
        InvoiceStatusHistory::query()->create([
            'invoice_id' => $invoice->id,
            'from_status' => $fromStatus ?? $invoice->status,
            'to_status' => $toStatus,
            'changed_by' => $request->user()?->id,
            'changed_at' => now(),
            'note' => $note,
        ]);
    }

    private function buildMinimalPdf(Invoice $invoice): string
    {
        $lines = [
            'COMPANY SHIP / CETA',
            'INVOICE '.$invoice->code,
            'Customer: '.($invoice->customer?->name ?? '#'.$invoice->customer_id),
            'Trip: '.($invoice->trip?->code ?? ($invoice->trip_id ? '#'.$invoice->trip_id : '-')),
            'Status: '.$invoice->status,
            'Subtotal: '.$invoice->subtotal,
            'VAT: '.$invoice->vat_amount,
            'Total: '.$invoice->total_amount,
            'Issued at: '.($invoice->issued_at?->format('Y-m-d H:i:s') ?? '-'),
            'Paid at: '.($invoice->paid_at?->format('Y-m-d H:i:s') ?? '-'),
        ];

        $content = "BT\n/F1 12 Tf\n50 780 Td\n";
        foreach ($lines as $index => $line) {
            if ($index > 0) {
                $content .= "0 -18 Td\n";
            }
            $content .= '('.$this->escapePdfText($line).") Tj\n";
        }
        $content .= 'ET';

        $objects = [
            "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n",
            "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n",
            "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n",
            "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n",
            "5 0 obj\n<< /Length ".strlen($content)." >>\nstream\n".$content."\nendstream\nendobj\n",
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $object) {
            $offsets[] = strlen($pdf);
            $pdf .= $object;
        }

        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";
        for ($i = 1; $i <= count($objects); $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }
        $pdf .= "trailer\n<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF";

        return $pdf;
    }

    private function escapePdfText(string $text): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    }
}
