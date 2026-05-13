<?php

declare(strict_types=1);

namespace App\Services\Vehicle;

use App\Models\Vehicle;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class VehicleService
{
    /**
     * @return LengthAwarePaginator<int, Vehicle>
     */
    public function paginateForCompany(int $companyId, int $perPage = 15): LengthAwarePaginator
    {
        return $this->baseCompanyQuery($companyId)
            ->latest('id')
            ->paginate($perPage);
    }

    /**
     * @return LengthAwarePaginator<int, Vehicle>
     */
    public function paginateAvailableForCompany(int $companyId, int $perPage = 15): LengthAwarePaginator
    {
        return $this->baseCompanyQuery($companyId)
            ->where('status', 'active')
            ->latest('id')
            ->paginate($perPage);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes, int $companyId): Vehicle
    {
        return DB::transaction(function () use ($attributes, $companyId): Vehicle {
            return Vehicle::create(array_merge($attributes, ['company_id' => $companyId]));
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Vehicle $vehicle, array $attributes): Vehicle
    {
        return DB::transaction(function () use ($vehicle, $attributes): Vehicle {
            $vehicle->update($attributes);

            return $vehicle->refresh();
        });
    }

    public function updateStatus(Vehicle $vehicle, string $status): Vehicle
    {
        return $this->update($vehicle, ['status' => $status]);
    }

    public function delete(Vehicle $vehicle): void
    {
        DB::transaction(static function () use ($vehicle): void {
            $vehicle->delete();
        });
    }

    private function baseCompanyQuery(int $companyId): Builder
    {
        return Vehicle::query()
            ->select([
                'id',
                'company_id',
                'vehicle_type_id',
                'plate_number',
                'type',
                'brand',
                'model',
                'year',
                'capacity',
                'max_load_ton',
                'current_odometer_km',
                'status',
                'created_at',
                'updated_at',
            ])
            ->where('company_id', $companyId);
    }
}
