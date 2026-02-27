<?php

declare(strict_types=1);

namespace Src\Domain\Employee\Repositories;

use Src\Domain\Employee\Entities\Employee;
use Src\Domain\Employee\ValueObjects\EmployeeId;
use Src\Domain\Employee\ValueObjects\EmployeeCode;
use Src\Domain\Shared\ValueObjects\Email;

interface EmployeeRepositoryInterface
{
    public function findById(EmployeeId $id): ?Employee;

    public function findByEmail(Email $email): ?Employee;

    public function findByCode(EmployeeCode $code): ?Employee;

    public function save(Employee $employee): void;

    public function delete(EmployeeId $id): void;

    public function existsByEmail(Email $email, ?EmployeeId $excludeId = null): bool;

    public function existsByCode(EmployeeCode $code, ?EmployeeId $excludeId = null): bool;

    public function hasPayrollRecords(EmployeeId $id): bool;

    public function hasActiveVehicleAssignments(EmployeeId $id): bool;
}
