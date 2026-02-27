<?php

declare(strict_types=1);

namespace Src\Application\Employee\DTOs;

final readonly class CreateEmployeeData
{
    public function __construct(
        public string $code,
        public string $name,
        public string $email,
        public string $type,
        public string $joinDate,
        public int $officeId,
        public int $positionId,
        public ?string $phone = null,
        public ?string $dob = null,
        public ?string $gender = null,
        public ?string $address = null,
        public ?int $departmentId = null,
        public ?string $status = null
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            code: (string) $data['code'],
            name: (string) $data['name'],
            email: (string) $data['email'],
            type: (string) $data['type'],
            joinDate: (string) $data['join_date'],
            officeId: (int) $data['office_id'],
            positionId: (int) $data['position_id'],
            phone: isset($data['phone']) ? (string) $data['phone'] : null,
            dob: isset($data['dob']) ? (string) $data['dob'] : null,
            gender: isset($data['gender']) ? (string) $data['gender'] : null,
            address: isset($data['address']) ? (string) $data['address'] : null,
            departmentId: isset($data['department_id']) ? (int) $data['department_id'] : null,
            status: isset($data['status']) ? (string) $data['status'] : null
        );
    }
}
