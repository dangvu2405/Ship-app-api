<?php

declare(strict_types=1);

namespace Src\Interface\Http\Resources\Employee;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Application\Employee\DTOs\EmployeeResult;

/**
 * @mixin EmployeeResult
 */
final class EmployeeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
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
