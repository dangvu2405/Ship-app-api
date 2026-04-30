<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\VehicleDocument\StoreVehicleDocumentRequest;
use App\Http\Requests\VehicleDocument\UpdateVehicleDocumentRequest;
use App\Http\Traits\HasIndexQuery;
use App\Models\User;
use App\Models\VehicleDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

final class VehicleDocumentController extends BaseController
{
    use HasIndexQuery;

    protected array $allowedSortColumns = ['id', 'doc_type', 'expiry_date', 'created_at'];

    public function index(Request $request): JsonResponse
    {
        $query = VehicleDocument::query();
        $result = $this->indexQuery($request, $query, ['doc_name', 'doc_number', 'doc_type'], [
            'vehicle_id' => 'vehicle_id',
            'doc_type' => 'doc_type',
        ]);

        return $this->successResponse($result, 'OK');
    }

    public function store(StoreVehicleDocumentRequest $request): JsonResponse
    {
        $document = VehicleDocument::query()->create($request->validated());
        $this->notifyExpiringDocument($document);

        return $this->successResponse($document, 'Vehicle document created successfully', 201);
    }

    public function show(string $vehicleDocument): JsonResponse
    {
        $model = VehicleDocument::query()->find($vehicleDocument);
        if ($model === null) {
            return $this->notFoundResponse('Vehicle document not found');
        }

        return $this->successResponse($model);
    }

    public function update(UpdateVehicleDocumentRequest $request, string $vehicleDocument): JsonResponse
    {
        $model = VehicleDocument::query()->find($vehicleDocument);
        if ($model === null) {
            return $this->notFoundResponse('Vehicle document not found');
        }

        $model->update($request->validated());
        $this->notifyExpiringDocument($model->fresh());

        return $this->successResponse($model->fresh(), 'Vehicle document updated successfully');
    }

    public function destroy(string $vehicleDocument): JsonResponse
    {
        $model = VehicleDocument::query()->find($vehicleDocument);
        if ($model === null) {
            return $this->notFoundResponse('Vehicle document not found');
        }

        $model->delete();

        return $this->successResponse(null, 'Vehicle document deleted successfully');
    }

    private function notifyExpiringDocument(VehicleDocument $document): void
    {
        if ($document->expiry_date === null) {
            return;
        }

        $daysUntilExpiry = now()->startOfDay()->diffInDays($document->expiry_date->startOfDay(), false);
        if ($daysUntilExpiry < 0 || $daysUntilExpiry > (int) $document->alert_before_days) {
            return;
        }

        $usersQuery = User::query()
            ->where(function ($query): void {
                $query->where('role', 'admin')
                    ->orWhere('role', 'dispatcher');
            });

        if (Schema::hasColumn('users', 'company_id')) {
            $usersQuery->where('company_id', $document->company_id);
        }

        $users = $usersQuery->get(['id']);

        foreach ($users as $user) {
            DB::table('notifications')->insert([
                'id' => (string) Str::uuid(),
                'type' => 'vehicle_document_expiry',
                'notifiable_type' => User::class,
                'notifiable_id' => $user->id,
                'data' => json_encode([
                    'vehicle_document_id' => $document->id,
                    'vehicle_id' => $document->vehicle_id,
                    'doc_type' => $document->doc_type,
                    'expiry_date' => $document->expiry_date?->toDateString(),
                ], JSON_THROW_ON_ERROR),
                'read_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
