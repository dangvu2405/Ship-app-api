<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Services\ShippingFeeService;
use App\Http\Requests\ShippingFee\CalculateShippingFeeRequest;
use App\Http\Resources\ShippingFeeResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ShippingFeeController extends BaseController
{
    private ShippingFeeService $shippingFeeService;

    public function __construct(ShippingFeeService $shippingFeeService)
    {
        $this->shippingFeeService = $shippingFeeService;
    }

    public function lookup(CalculateShippingFeeRequest $request): ShippingFeeResource
    {
        $result = $this->shippingFeeService->calculate(
            $request->input('origin'),
            $request->input('destination'),
            $request->filled('vehicle_type_id') ? (int) $request->input('vehicle_type_id') : null
        );

        if (!$result['success']) {
            abort(422, $result['message']);
        }

        return new ShippingFeeResource((object) $result);
    }
}
