<?php

declare(strict_types=1);

namespace Src\Domain\Employee\Exceptions;

use Src\Domain\Shared\Exceptions\DomainException;

final class EmployeeAlreadyExistsException extends DomainException
{
    public static function withEmail(string $email): self
    {
        return new self(
            "Employee with email '{$email}' already exists",
            'EMPLOYEE_EMAIL_EXISTS'
        );
    }

    public static function withCode(string $code): self
    {
        return new self(
            "Employee with code '{$code}' already exists",
            'EMPLOYEE_CODE_EXISTS'
        );
    }
}
