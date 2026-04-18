<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\PublicHoliday\IndexPublicHolidayRequest;
use App\Models\PublicHoliday;
use Illuminate\Http\JsonResponse;

final class PublicHolidayController extends BaseController
{
    public function index(IndexPublicHolidayRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $countryCode = $validated['country_code'] ?? 'VN';

        $data = PublicHoliday::query()
            ->where('year', (int) $validated['year'])
            ->where('country_code', $countryCode)
            ->orderBy('date')
            ->get();

        return $this->successResponse($data, 'Public holidays retrieved.');
    }
}
