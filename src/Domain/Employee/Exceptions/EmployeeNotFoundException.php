<?php

declare(strict_types=1);

namespace Src\Domain\Employee\Exceptions;

use Src\Domain\Shared\Exceptions\EntityNotFoundException;

final class EmployeeNotFoundException extends EntityNotFoundException
{
    public static function withId(string $id): self
    {
        return new self(
            "Employee with ID '{$id}' not found",
            'EMPLOYEE_NOT_FOUND'
        );
    }

    public static function withEmail(string $email): self
    {
        return new self(
            "Employee with email '{$email}' not found",
            'EMPLOYEE_NOT_FOUND'
        );
    }

    public static function withCode(string $code): self
    {
        return new self(
            "Employee with code '{$code}' not found",
            'EMPLOYEE_NOT_FOUND'
        );
    }
}
