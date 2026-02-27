<?php

declare(strict_types=1);

namespace Src\Application\Employee\UseCases;

use DateTimeImmutable;
use Psr\Log\LoggerInterface;
use Src\Application\Employee\DTOs\EmployeeResult;
use Src\Application\Employee\DTOs\UpdateEmployeeData;
use Src\Application\Shared\Contracts\TransactionManagerInterface;
use Src\Domain\Employee\Exceptions\EmployeeAlreadyExistsException;
use Src\Domain\Employee\Exceptions\EmployeeNotFoundException;
use Src\Domain\Employee\Repositories\EmployeeRepositoryInterface;
use Src\Domain\Employee\ValueObjects\EmployeeCode;
use Src\Domain\Employee\ValueObjects\EmployeeId;
use Src\Domain\Employee\ValueObjects\EmployeeStatus;
use Src\Domain\Employee\ValueObjects\EmployeeType;
use Src\Domain\Employee\ValueObjects\Gender;
use Src\Domain\Shared\ValueObjects\Email;
use Src\Domain\Shared\ValueObjects\Phone;

final readonly class UpdateEmployeeUseCase
{
    public function __construct(
        private EmployeeRepositoryInterface $employeeRepository,
        private TransactionManagerInterface $transactionManager,
        private LoggerInterface $logger
    ) {}

    public function execute(string $id, UpdateEmployeeData $data): EmployeeResult
    {
        $employeeId = EmployeeId::fromString($id);
        $employee = $this->employeeRepository->findById($employeeId);

        if ($employee === null) {
            throw EmployeeNotFoundException::withId($id);
        }

        // Check email uniqueness if updating
        if ($data->email !== null) {
            $newEmail = Email::fromString($data->email);
            if ($this->employeeRepository->existsByEmail($newEmail, $employeeId)) {
                throw EmployeeAlreadyExistsException::withEmail($data->email);
            }
        }

        // Check code uniqueness if updating
        if ($data->code !== null) {
            $newCode = EmployeeCode::fromString($data->code);
            if ($this->employeeRepository->existsByCode($newCode, $employeeId)) {
                throw EmployeeAlreadyExistsException::withCode($data->code);
            }
        }

        // Update entity
        $employee->update(
            code: $data->code !== null ? EmployeeCode::fromString($data->code) : null,
            name: $data->name,
            email: $data->email !== null ? Email::fromString($data->email) : null,
            phone: $data->phone !== null ? Phone::fromStringOrNull($data->phone) : null,
            dob: $data->dob !== null ? new DateTimeImmutable($data->dob) : null,
            gender: $data->gender !== null ? Gender::fromStringOrNull($data->gender) : null,
            address: $data->address,
            type: $data->type !== null ? EmployeeType::from($data->type) : null,
            status: $data->status !== null ? EmployeeStatus::from($data->status) : null,
            joinDate: $data->joinDate !== null ? new DateTimeImmutable($data->joinDate) : null,
            officeId: $data->officeId,
            departmentId: $data->departmentId,
            positionId: $data->positionId
        );

        // Handle resignation
        if ($data->resignDate !== null) {
            $employee->resign(new DateTimeImmutable($data->resignDate));
        }

        // Persist
        $this->transactionManager->execute(function () use ($employee): void {
            $this->employeeRepository->save($employee);
        });

        $this->logger->info('Employee updated', [
            'employee_id' => $employee->id()->value(),
        ]);

        return EmployeeResult::fromEntity($employee);
    }
}
