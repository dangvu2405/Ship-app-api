<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;

class FallbackController extends BaseController
{
    /**
     * Handle unmatched API routes under /api.
     */
    public function handle(Request $request)
    {
        $path = $request->path();
        return $this->errorResponse('api.not_implemented', 501, ['path' => $path]);
    }
}
