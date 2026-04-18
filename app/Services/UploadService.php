<?php

declare(strict_types=1);

namespace App\Services;

use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;
use Illuminate\Http\UploadedFile;

final readonly class UploadService
{
    /**
     * Giao tiếp với API Cloudinary để upload file.
     *
     * @param UploadedFile $file
     * @param string $folder Thư mục trên Cloudinary
     * @return array{url: string, public_id: string}
     */
    public function uploadImage(UploadedFile $file, string $folder = 'ship_app'): array
    {
        $uploadedFileUrl = cloudinary()->upload($file->getRealPath(), [
            'folder' => $folder,
        ]);

        return [
            'url'       => $uploadedFileUrl->getSecurePath(),
            'public_id' => $uploadedFileUrl->getPublicId(),
        ];
    }
}
