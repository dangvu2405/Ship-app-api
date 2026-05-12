<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Models\Driver;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Resources\DriverResource;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;

class DriverController extends BaseController
{
    public function index(Request $request): JsonResource
    {
        $drivers = Driver::query()
            ->where('company_id', auth()->user()->company_id)
            ->paginate(15);

        return DriverResource::collection($drivers);
    }

    public function store(Request $request): JsonResponse
    {
        // TODO: Replace with a dedicated FormRequest
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:drivers',
            'phone' => 'required|string|max:255|unique:drivers',
            'dob' => 'nullable|date',
            'gender' => 'nullable|string|in:male,female,other',
            'address' => 'nullable|string|max:255',
            'license_no' => 'required|string|max:255|unique:drivers',
            'license_class' => 'required|string|max:255',
            'expired_date' => 'nullable|date',
            'join_date' => 'nullable|date',
            'resign_date' => 'nullable|date',
            'status' => 'nullable|string|in:active,inactive,resigned',
            'available_status' => 'nullable|string|in:available,busy,offline',
        ]);

        $driver = DB::transaction(function () use ($validated) {
            $driver = Driver::create(array_merge($validated, [
                'company_id' => auth()->user()->company_id,
            ]));
            return $driver;
        });

        return (new DriverResource($driver))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Driver $driver): JsonResource
    {
        $this->authorize('view', $driver);

        return new DriverResource($driver);
    }

    public function update(Request $request, Driver $driver): JsonResource
    {
        $this->authorize('update', $driver);

        // TODO: Replace with a dedicated FormRequest
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|string|email|max:255|unique:drivers,email,' . $driver->id,
            'phone' => 'sometimes|required|string|max:255|unique:drivers,phone,' . $driver->id,
            'dob' => 'sometimes|nullable|date',
            'gender' => 'sometimes|nullable|string|in:male,female,other',
            'address' => 'sometimes|nullable|string|max:255',
            'license_no' => 'sometimes|required|string|max:255|unique:drivers,license_no,' . $driver->id,
            'license_class' => 'sometimes|required|string|max:255',
            'expired_date' => 'sometimes|nullable|date',
            'join_date' => 'sometimes|nullable|date',
            'resign_date' => 'sometimes|nullable|date',
            'status' => 'sometimes|nullable|string|in:active,inactive,resigned',
            'available_status' => 'sometimes|nullable|string|in:available,busy,offline',
        ]);

        DB::transaction(function () use ($validated, $driver) {
            $driver->update($validated);
        });

        return new DriverResource($driver);
    }

    public function destroy(Driver $driver): JsonResponse
    {
        $this->authorize('delete', $driver);

        DB::transaction(function () use ($driver) {
            $driver->delete();
        });

        return response()->json(null, 204);
    }
}
