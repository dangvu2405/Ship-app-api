<?php

declare(strict_types=1);

namespace Src\Infrastructure\Persistence\Mappers;

use DateTimeImmutable;
use Src\Domain\Employee\Entities\Employee;
use Src\Domain\Employee\ValueObjects\EmployeeCode;
use Src\Domain\Employee\ValueObjects\EmployeeId;
use Src\Domain\Employee\ValueObjects\EmployeeStatus;
use Src\Domain\Employee\ValueObjects\EmployeeType;
use Src\Domain\Employee\ValueObjects\Gender;
use Src\Domain\Shared\ValueObjects\Email;
use Src\Domain\Shared\ValueObjects\Phone;
use Src\Infrastructure\Persistence\Eloquent\Models\EmployeeModel;

final class EmployeeMapper
{
    public function toDomain(EmployeeModel $model): Employee
    {
        return Employee::reconstitute(
            id: EmployeeId::fromString($model->id),
            code: EmployeeCode::fromString($model->code),
            name: $model->name,
            email: Email::fromString($model->email),
            phone: Phone::fromStringOrNull($model->phone),
            dob: $model->dob !== null
                ? new DateTimeImmutable($model->dob->format('Y-m-d'))
                : null,
            gender: Gender::fromStringOrNull($model->gender),
            address: $model->address,
            type: EmployeeType::from($model->type),
            status: EmployeeStatus::from($model->status),
            joinDate: new DateTimeImmutable($model->join_date->format('Y-m-d')),
            resignDate: $model->resign_date !== null
                ? new DateTimeImmutable($model->resign_date->format('Y-m-d'))
                : null,
            officeId: $model->office_id,
            departmentId: $model->department_id,
            positionId: $model->position_id,
            createdAt: new DateTimeImmutable($model->created_at->format('Y-m-d H:i:s')),
            updatedAt: new DateTimeImmutable($model->updated_at->format('Y-m-d H:i:s'))
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toPersistence(Employee $entity): array
    {
        return [
            'id' => $entity->id()->value(),
            'code' => $entity->code()->value(),
            'name' => $entity->name(),
            'email' => $entity->email()->value(),
            'phone' => $entity->phone()?->value(),
            'dob' => $entity->dob()?->format('Y-m-d'),
            'gender' => $entity->gender()?->value(),
            'address' => $entity->address(),
            'type' => $entity->type()->value(),
            'status' => $entity->status()->value(),
            'join_date' => $entity->joinDate()->format('Y-m-d'),
            'resign_date' => $entity->resignDate()?->format('Y-m-d'),
            'office_id' => $entity->officeId(),
            'department_id' => $entity->departmentId(),
            'position_id' => $entity->positionId(),
        ];
    }
}
