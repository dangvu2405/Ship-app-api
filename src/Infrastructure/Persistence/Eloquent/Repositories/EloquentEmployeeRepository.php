<?php

declare(strict_types=1);

namespace Src\Infrastructure\Persistence\Eloquent\Repositories;

use Src\Domain\Employee\Entities\Employee;
use Src\Domain\Employee\Repositories\EmployeeRepositoryInterface;
use Src\Domain\Employee\ValueObjects\EmployeeCode;
use Src\Domain\Employee\ValueObjects\EmployeeId;
use Src\Domain\Shared\ValueObjects\Email;
use Src\Infrastructure\Persistence\Eloquent\Models\EmployeeModel;
use Src\Infrastructure\Persistence\Mappers\EmployeeMapper;

final readonly class EloquentEmployeeRepository implements EmployeeRepositoryInterface
{
    public function __construct(
        private EmployeeMapper $mapper
    ) {}

    public function findById(EmployeeId $id): ?Employee
    {
        $model = EmployeeModel::query()->find($id->value());

        return $model !== null ? $this->mapper->toDomain($model) : null;
    }

    public function findByEmail(Email $email): ?Employee
    {
        $model = EmployeeModel::query()
            ->where('email', $email->value())
            ->first();

        return $model !== null ? $this->mapper->toDomain($model) : null;
    }

    public function findByCode(EmployeeCode $code): ?Employee
    {
        $model = EmployeeModel::query()
            ->where('code', $code->value())
            ->first();

        return $model !== null ? $this->mapper->toDomain($model) : null;
    }

    public function save(Employee $employee): void
    {
        $data = $this->mapper->toPersistence($employee);

        EmployeeModel::query()->updateOrCreate(
            ['id' => $employee->id()->value()],
            $data
        );
    }

    public function delete(EmployeeId $id): void
    {
        EmployeeModel::query()
            ->where('id', $id->value())
            ->delete();
    }

    public function existsByEmail(Email $email, ?EmployeeId $excludeId = null): bool
    {
        $query = EmployeeModel::query()->where('email', $email->value());

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId->value());
        }

        return $query->exists();
    }

    public function existsByCode(EmployeeCode $code, ?EmployeeId $excludeId = null): bool
    {
        $query = EmployeeModel::query()->where('code', $code->value());

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId->value());
        }

        return $query->exists();
    }

    public function hasPayrollRecords(EmployeeId $id): bool
    {
        return EmployeeModel::query()
            ->where('id', $id->value())
            ->whereHas('payrollDetails')
            ->exists();
    }

    public function hasActiveVehicleAssignments(EmployeeId $id): bool
    {
        return EmployeeModel::query()
            ->where('id', $id->value())
            ->whereHas('vehicleAssignments', function ($query) {
                $query->whereNull('end_date')
                    ->orWhere('end_date', '>=', now()->toDateString());
            })
            ->exists();
    }
}
