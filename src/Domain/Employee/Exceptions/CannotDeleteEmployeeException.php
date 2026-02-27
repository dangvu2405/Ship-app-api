<?php

declare(strict_types=1);

namespace Src\Domain\Employee\Exceptions;

use Src\Domain\Shared\Exceptions\DomainException;

final class CannotDeleteEmployeeException extends DomainException
{
    public static function hasPayrollRecords(string $id): self
    {
        return new self(
            "Cannot delete employee '{$id}' because they have payroll records",
            'EMPLOYEE_HAS_PAYROLL'
        );
    }

    public static function isActive(string $id): self
    {
        return new self(
            "Cannot delete employee '{$id}' because they are still active",
            'EMPLOYEE_IS_ACTIVE'
        );
    }

    public static function hasActiveAssignments(string $id): self
    {
        return new self(
            "Cannot delete employee '{$id}' because they have active vehicle assignments",
            'EMPLOYEE_HAS_ASSIGNMENTS'
        );
    }
}
