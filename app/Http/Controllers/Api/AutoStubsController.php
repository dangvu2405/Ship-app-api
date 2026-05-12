<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;

class AutoStubsController extends BaseController
{
    /**
     * Generic not-implemented responder for auto-generated stubs.
     * Returns 501 with path metadata. Protected by middleware where registered.
     */
    public function notImplemented(Request $request)
    {
        $payload = [
            'path' => '/' . ltrim($request->path(), '/'),
            'method' => $request->method(),
            'hint' => 'This is an auto-generated stub. Implement business logic in Service layer.',
        ];

        return $this->errorResponse('api.not_implemented', 501, $payload);
    }
}
