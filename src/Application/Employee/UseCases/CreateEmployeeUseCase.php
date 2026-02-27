<?php

declare(strict_types=1);

namespace Src\Application\Employee\UseCases;

use DateTimeImmutable;
use Psr\Log\LoggerInterface;
use Src\Application\Employee\DTOs\CreateEmployeeData;
use Src\Application\Employee\DTOs\EmployeeResult;
use Src\Application\Shared\Contracts\EventDispatcherInterface;
use Src\Application\Shared\Contracts\TransactionManagerInterface;
use Src\Domain\Employee\Entities\Employee;
use Src\Domain\Employee\Exceptions\EmployeeAlreadyExistsException;
use Src\Domain\Employee\Repositories\EmployeeRepositoryInterface;
use Src\Domain\Employee\ValueObjects\EmployeeCode;
use Src\Domain\Employee\ValueObjects\EmployeeStatus;
use Src\Domain\Employee\ValueObjects\EmployeeType;
use Src\Domain\Employee\ValueObjects\Gender;
use Src\Domain\Shared\ValueObjects\Email;
use Src\Domain\Shared\ValueObjects\Phone;

final readonly class CreateEmployeeUseCase
{
    public function __construct(
        private EmployeeRepositoryInterface $employeeRepository,
        private TransactionManagerInterface $transactionManager,
        private EventDispatcherInterface $eventDispatcher,
        private LoggerInterface $logger
    ) {}

    public function execute(CreateEmployeeData $data): EmployeeResult
    {
        $email = Email::fromString($data->email);
        $code = EmployeeCode::fromString($data->code);

        // Business rule: Check uniqueness
        if ($this->employeeRepository->existsByEmail($email)) {
            throw EmployeeAlreadyExistsException::withEmail($data->email);
        }

        if ($this->employeeRepository->existsByCode($code)) {
            throw EmployeeAlreadyExistsException::withCode($data->code);
        }

        // Create domain entity
        $employee = Employee::create(
            code: $code,
            name: $data->name,
            email: $email,
            type: EmployeeType::from($data->type),
            joinDate: new DateTimeImmutable($data->joinDate),
            officeId: $data->officeId,
            positionId: $data->positionId,
            phone: Phone::fromStringOrNull($data->phone),
            dob: $data->dob !== null ? new DateTimeImmutable($data->dob) : null,
            gender: Gender::fromStringOrNull($data->gender),
            address: $data->address,
            departmentId: $data->departmentId,
            status: $data->status !== null ? EmployeeStatus::from($data->status) : null
        );

        // Persist within transaction
        $this->transactionManager->execute(function () use ($employee): void {
            $this->employeeRepository->save($employee);
        });

        // Log business event
        $this->logger->info('Employee created', [
            'employee_id' => $employee->id()->value(),
            'employee_code' => $employee->code()->value(),
        ]);

        return EmployeeResult::fromEntity($employee);
    }
}
