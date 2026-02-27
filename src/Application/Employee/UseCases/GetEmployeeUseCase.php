<?php

declare(strict_types=1);

namespace Src\Application\Employee\UseCases;

use Src\Application\Employee\DTOs\EmployeeResult;
use Src\Domain\Employee\Exceptions\EmployeeNotFoundException;
use Src\Domain\Employee\Repositories\EmployeeRepositoryInterface;
use Src\Domain\Employee\ValueObjects\EmployeeId;

final readonly class GetEmployeeUseCase
{
    public function __construct(
        private EmployeeRepositoryInterface $employeeRepository
    ) {}

    public function execute(string $id): EmployeeResult
    {
        $employeeId = EmployeeId::fromString($id);
        $employee = $this->employeeRepository->findById($employeeId);

        if ($employee === null) {
            throw EmployeeNotFoundException::withId($id);
        }

        return EmployeeResult::fromEntity($employee);
    }
}
