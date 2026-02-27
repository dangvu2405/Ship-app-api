<?php

declare(strict_types=1);

namespace Src\Domain\Employee\Entities;

use DateTimeImmutable;
use DomainException;
use Src\Domain\Employee\ValueObjects\EmployeeId;
use Src\Domain\Employee\ValueObjects\EmployeeCode;
use Src\Domain\Employee\ValueObjects\EmployeeType;
use Src\Domain\Employee\ValueObjects\EmployeeStatus;
use Src\Domain\Employee\ValueObjects\Gender;
use Src\Domain\Shared\ValueObjects\Email;
use Src\Domain\Shared\ValueObjects\Phone;

final class Employee
{
    private function __construct(
        private readonly EmployeeId $id,
        private EmployeeCode $code,
        private string $name,
        private Email $email,
        private ?Phone $phone,
        private ?DateTimeImmutable $dob,
        private ?Gender $gender,
        private ?string $address,
        private EmployeeType $type,
        private EmployeeStatus $status,
        private DateTimeImmutable $joinDate,
        private ?DateTimeImmutable $resignDate,
        private int $officeId,
        private ?int $departmentId,
        private int $positionId,
        private DateTimeImmutable $createdAt,
        private DateTimeImmutable $updatedAt
    ) {}

    public static function create(
        EmployeeCode $code,
        string $name,
        Email $email,
        EmployeeType $type,
        DateTimeImmutable $joinDate,
        int $officeId,
        int $positionId,
        ?Phone $phone = null,
        ?DateTimeImmutable $dob = null,
        ?Gender $gender = null,
        ?string $address = null,
        ?int $departmentId = null,
        ?EmployeeStatus $status = null
    ): self {
        return new self(
            id: EmployeeId::generate(),
            code: $code,
            name: trim($name),
            email: $email,
            phone: $phone,
            dob: $dob,
            gender: $gender,
            address: $address !== null ? trim($address) : null,
            type: $type,
            status: $status ?? EmployeeStatus::active(),
            joinDate: $joinDate,
            resignDate: null,
            officeId: $officeId,
            departmentId: $departmentId,
            positionId: $positionId,
            createdAt: new DateTimeImmutable(),
            updatedAt: new DateTimeImmutable()
        );
    }

    public static function reconstitute(
        EmployeeId $id,
        EmployeeCode $code,
        string $name,
        Email $email,
        ?Phone $phone,
        ?DateTimeImmutable $dob,
        ?Gender $gender,
        ?string $address,
        EmployeeType $type,
        EmployeeStatus $status,
        DateTimeImmutable $joinDate,
        ?DateTimeImmutable $resignDate,
        int $officeId,
        ?int $departmentId,
        int $positionId,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt
    ): self {
        return new self(
            id: $id,
            code: $code,
            name: $name,
            email: $email,
            phone: $phone,
            dob: $dob,
            gender: $gender,
            address: $address,
            type: $type,
            status: $status,
            joinDate: $joinDate,
            resignDate: $resignDate,
            officeId: $officeId,
            departmentId: $departmentId,
            positionId: $positionId,
            createdAt: $createdAt,
            updatedAt: $updatedAt
        );
    }

    public function update(
        ?EmployeeCode $code = null,
        ?string $name = null,
        ?Email $email = null,
        ?Phone $phone = null,
        ?DateTimeImmutable $dob = null,
        ?Gender $gender = null,
        ?string $address = null,
        ?EmployeeType $type = null,
        ?EmployeeStatus $status = null,
        ?DateTimeImmutable $joinDate = null,
        ?int $officeId = null,
        ?int $departmentId = null,
        ?int $positionId = null
    ): void {
        if ($code !== null) {
            $this->code = $code;
        }
        if ($name !== null) {
            $this->name = trim($name);
        }
        if ($email !== null) {
            $this->email = $email;
        }
        if ($phone !== null) {
            $this->phone = $phone;
        }
        if ($dob !== null) {
            $this->dob = $dob;
        }
        if ($gender !== null) {
            $this->gender = $gender;
        }
        if ($address !== null) {
            $this->address = trim($address);
        }
        if ($type !== null) {
            $this->type = $type;
        }
        if ($status !== null) {
            $this->status = $status;
        }
        if ($joinDate !== null) {
            $this->joinDate = $joinDate;
        }
        if ($officeId !== null) {
            $this->officeId = $officeId;
        }
        if ($departmentId !== null) {
            $this->departmentId = $departmentId;
        }
        if ($positionId !== null) {
            $this->positionId = $positionId;
        }

        $this->updatedAt = new DateTimeImmutable();
    }

    public function resign(DateTimeImmutable $resignDate): void
    {
        if ($resignDate < $this->joinDate) {
            throw new DomainException('Resign date cannot be before join date');
        }

        if ($this->status->isResigned()) {
            throw new DomainException('Employee has already resigned');
        }

        $this->resignDate = $resignDate;
        $this->status = EmployeeStatus::resigned();
        $this->updatedAt = new DateTimeImmutable();
    }

    public function activate(): void
    {
        if ($this->status->isActive()) {
            throw new DomainException('Employee is already active');
        }

        if ($this->status->isResigned()) {
            throw new DomainException('Cannot activate a resigned employee');
        }

        $this->status = EmployeeStatus::active();
        $this->updatedAt = new DateTimeImmutable();
    }

    public function deactivate(): void
    {
        if ($this->status->isInactive()) {
            throw new DomainException('Employee is already inactive');
        }

        if ($this->status->isResigned()) {
            throw new DomainException('Cannot deactivate a resigned employee');
        }

        $this->status = EmployeeStatus::inactive();
        $this->updatedAt = new DateTimeImmutable();
    }

    public function isActive(): bool
    {
        return $this->status->isActive();
    }

    public function isDriver(): bool
    {
        return $this->type->isDriver();
    }

    public function isOfficeStaff(): bool
    {
        return $this->type->isOffice();
    }

    public function canBeDeleted(): bool
    {
        return !$this->status->isActive();
    }

    // Getters
    public function id(): EmployeeId
    {
        return $this->id;
    }

    public function code(): EmployeeCode
    {
        return $this->code;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function email(): Email
    {
        return $this->email;
    }

    public function phone(): ?Phone
    {
        return $this->phone;
    }

    public function dob(): ?DateTimeImmutable
    {
        return $this->dob;
    }

    public function gender(): ?Gender
    {
        return $this->gender;
    }

    public function address(): ?string
    {
        return $this->address;
    }

    public function type(): EmployeeType
    {
        return $this->type;
    }

    public function status(): EmployeeStatus
    {
        return $this->status;
    }

    public function joinDate(): DateTimeImmutable
    {
        return $this->joinDate;
    }

    public function resignDate(): ?DateTimeImmutable
    {
        return $this->resignDate;
    }

    public function officeId(): int
    {
        return $this->officeId;
    }

    public function departmentId(): ?int
    {
        return $this->departmentId;
    }

    public function positionId(): int
    {
        return $this->positionId;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
