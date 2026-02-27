<?php

declare(strict_types=1);

namespace Src\Application\Employee\DTOs;

final readonly class UpdateEmployeeData
{
    public function __construct(
        public ?string $code = null,
        public ?string $name = null,
        public ?string $email = null,
        public ?string $type = null,
        public ?string $joinDate = null,
        public ?int $officeId = null,
        public ?int $positionId = null,
        public ?string $phone = null,
        public ?string $dob = null,
        public ?string $gender = null,
        public ?string $address = null,
        public ?int $departmentId = null,
        public ?string $status = null,
        public ?string $resignDate = null
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            code: isset($data['code']) ? (string) $data['code'] : null,
            name: isset($data['name']) ? (string) $data['name'] : null,
            email: isset($data['email']) ? (string) $data['email'] : null,
            type: isset($data['type']) ? (string) $data['type'] : null,
            joinDate: isset($data['join_date']) ? (string) $data['join_date'] : null,
            officeId: isset($data['office_id']) ? (int) $data['office_id'] : null,
            positionId: isset($data['position_id']) ? (int) $data['position_id'] : null,
            phone: isset($data['phone']) ? (string) $data['phone'] : null,
            dob: isset($data['dob']) ? (string) $data['dob'] : null,
            gender: isset($data['gender']) ? (string) $data['gender'] : null,
            address: isset($data['address']) ? (string) $data['address'] : null,
            departmentId: isset($data['department_id']) ? (int) $data['department_id'] : null,
            status: isset($data['status']) ? (string) $data['status'] : null,
            resignDate: isset($data['resign_date']) ? (string) $data['resign_date'] : null
        );
    }

    public function hasChanges(): bool
    {
        return $this->code !== null
            || $this->name !== null
            || $this->email !== null
            || $this->type !== null
            || $this->joinDate !== null
            || $this->officeId !== null
            || $this->positionId !== null
            || $this->phone !== null
            || $this->dob !== null
            || $this->gender !== null
            || $this->address !== null
            || $this->departmentId !== null
            || $this->status !== null
            || $this->resignDate !== null;
    }
}
