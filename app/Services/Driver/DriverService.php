<?php

declare(strict_types=1);

namespace App\Services\Driver;

use App\Models\Driver;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DriverService
{
    /**
     * @return LengthAwarePaginator<int, Driver>
     */
    public function paginateForCompany(int $companyId, int $perPage = 15): LengthAwarePaginator
    {
        return Driver::query()
            ->select([
                'id',
                'company_id',
                'code',
                'name',
                'email',
                'phone',
                'dob',
                'gender',
                'address',
                'avatar_url',
                'license_no',
                'license_class',
                'expired_date',
                'available_status',
                'status',
                'join_date',
                'resign_date',
                'created_at',
                'updated_at',
            ])
            ->where('company_id', $companyId)
            ->latest('id')
            ->paginate($perPage);
    }

    /**
     * @return LengthAwarePaginator<int, Driver>
     */
    public function paginateAvailableForCompany(int $companyId, int $perPage = 15): LengthAwarePaginator
    {
        return Driver::query()
            ->select([
                'id',
                'company_id',
                'code',
                'name',
                'email',
                'phone',
                'license_no',
                'license_class',
                'expired_date',
                'available_status',
                'status',
                'created_at',
                'updated_at',
            ])
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->where('available_status', 'available')
            ->latest('id')
            ->paginate($perPage);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes, int $companyId): Driver
    {
        return DB::transaction(function () use ($attributes, $companyId): Driver {
            return Driver::create(array_merge($attributes, [
                'company_id' => $companyId,
                'code' => $this->generateCode(),
            ]));
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Driver $driver, array $attributes): Driver
    {
        return DB::transaction(function () use ($driver, $attributes): Driver {
            $driver->update($attributes);

            return $driver->refresh();
        });
    }

    public function delete(Driver $driver): void
    {
        DB::transaction(static function () use ($driver): void {
            $driver->delete();
        });
    }

    private function generateCode(): string
    {
        do {
            $code = 'DRV-'.strtoupper(Str::random(8));
        } while (Driver::query()->where('code', $code)->exists());

        return $code;
    }
}
