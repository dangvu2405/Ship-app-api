<?php

declare(strict_types=1);

namespace Src\Application\Employee\UseCases;

use Psr\Log\LoggerInterface;
use Src\Application\Shared\Contracts\TransactionManagerInterface;
use Src\Domain\Employee\Exceptions\CannotDeleteEmployeeException;
use Src\Domain\Employee\Exceptions\EmployeeNotFoundException;
use Src\Domain\Employee\Repositories\EmployeeRepositoryInterface;
use Src\Domain\Employee\ValueObjects\EmployeeId;

final readonly class DeleteEmployeeUseCase
{
    public function __construct(
        private EmployeeRepositoryInterface $employeeRepository,
        private TransactionManagerInterface $transactionManager,
        private LoggerInterface $logger
    ) {}

    public function execute(string $id): void
    {
        $employeeId = EmployeeId::fromString($id);
        $employee = $this->employeeRepository->findById($employeeId);

        if ($employee === null) {
            throw EmployeeNotFoundException::withId($id);
        }

        // Business rules: Cannot delete if has payroll records
        if ($this->employeeRepository->hasPayrollRecords($employeeId)) {
            throw CannotDeleteEmployeeException::hasPayrollRecords($id);
        }

        // Business rules: Cannot delete if has active vehicle assignments
        if ($this->employeeRepository->hasActiveVehicleAssignments($employeeId)) {
            throw CannotDeleteEmployeeException::hasActiveAssignments($id);
        }

        // Persist deletion
        $this->transactionManager->execute(function () use ($employeeId): void {
            $this->employeeRepository->delete($employeeId);
        });

        $this->logger->info('Employee deleted', [
            'employee_id' => $id,
        ]);
    }
}
