<?php

declare(strict_types=1);

namespace Src\Application\Employee\DTOs;

use Src\Domain\Employee\Entities\Employee;

final readonly class EmployeeResult
{
    public function __construct(
        public string $id,
        public string $code,
        public string $name,
        public string $email,
        public ?string $phone,
        public ?string $dob,
        public ?string $gender,
        public ?string $address,
        public string $type,
        public string $status,
        public string $joinDate,
        public ?string $resignDate,
        public int $officeId,
        public ?int $departmentId,
        public int $positionId,
        public string $createdAt,
        public string $updatedAt
    ) {}

    public static function fromEntity(Employee $employee): self
    {
        return new self(
            id: $employee->id()->value(),
            code: $employee->code()->value(),
            name: $employee->name(),
            email: $employee->email()->value(),
            phone: $employee->phone()?->value(),
            dob: $employee->dob()?->format('Y-m-d'),
            gender: $employee->gender()?->value(),
            address: $employee->address(),
            type: $employee->type()->value(),
            status: $employee->status()->value(),
            joinDate: $employee->joinDate()->format('Y-m-d'),
            resignDate: $employee->resignDate()?->format('Y-m-d'),
            officeId: $employee->officeId(),
            departmentId: $employee->departmentId(),
            positionId: $employee->positionId(),
            createdAt: $employee->createdAt()->format('Y-m-d H:i:s'),
            updatedAt: $employee->updatedAt()->format('Y-m-d H:i:s')
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'dob' => $this->dob,
            'gender' => $this->gender,
            'address' => $this->address,
            'type' => $this->type,
            'status' => $this->status,
            'join_date' => $this->joinDate,
            'resign_date' => $this->resignDate,
            'office_id' => $this->officeId,
            'department_id' => $this->departmentId,
            'position_id' => $this->positionId,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}
