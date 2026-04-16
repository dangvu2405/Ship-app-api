<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Models\PublicHoliday;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PublicHolidayController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'country_code' => ['nullable', 'string', 'max:5'],
        ]);

        $countryCode = $validated['country_code'] ?? 'VN';

        $data = PublicHoliday::query()
            ->where('year', (int) $validated['year'])
            ->where('country_code', $countryCode)
            ->orderBy('date')
            ->get();

        return $this->successResponse($data, 'Public holidays retrieved.');
    }
}
