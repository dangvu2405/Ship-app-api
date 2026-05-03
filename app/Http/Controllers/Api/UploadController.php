<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\UploadDocumentRequest;
use App\Http\Requests\UploadImageRequest;
use App\Services\UploadService;
use Illuminate\Http\JsonResponse;

final class UploadController extends BaseController
{
    public function __construct(
        private readonly UploadService $uploadService
    ) {}

    /**
     * Xử lý request upload ảnh.
     */
    public function store(UploadImageRequest $request): JsonResponse
    {
        try {
            /** @var \Illuminate\Http\UploadedFile $file */
            $file = $request->file('file');
            
            $result = $this->uploadService->uploadImage($file, 'ship_app_uploads');

            return $this->successResponse(
                data: $result,
                message: 'api.upload.success',
                code: 201
            );
        } catch (\Throwable $e) {
            return $this->handleException($e, 'api.upload.failed');
        }
    }

    public function storeImage(UploadImageRequest $request): JsonResponse
    {
        return $this->store($request);
    }

    public function storeDocument(UploadDocumentRequest $request): JsonResponse
    {
        try {
            /** @var \Illuminate\Http\UploadedFile $file */
            $file = $request->file('file');
            $result = $this->uploadService->uploadImage($file, 'ship_app_documents');

            return $this->successResponse(
                data: $result,
                message: 'api.upload.success',
                code: 201
            );
        } catch (\Throwable $e) {
            return $this->handleException($e, 'api.upload.failed');
        }
    }
}
