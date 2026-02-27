<?php

declare(strict_types=1);

namespace Src\Application\Employee\DTOs;

final readonly class ListEmployeesCriteria
{
    public function __construct(
        public int $page = 1,
        public int $perPage = 15,
        public ?string $keyword = null,
        public string $sortBy = 'id',
        public string $sortOrder = 'desc',
        public ?int $officeId = null,
        public ?int $departmentId = null,
        public ?string $type = null,
        public ?string $status = null
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $perPage = isset($data['per_page']) ? min((int) $data['per_page'], 100) : 15;
        $perPage = $perPage > 0 ? $perPage : 15;

        return new self(
            page: isset($data['page']) ? max((int) $data['page'], 1) : 1,
            perPage: $perPage,
            keyword: $data['keyword'] ?? $data['q'] ?? null,
            sortBy: $data['sort_by'] ?? 'id',
            sortOrder: strtolower($data['sort_order'] ?? 'desc') === 'asc' ? 'asc' : 'desc',
            officeId: isset($data['office_id']) ? (int) $data['office_id'] : null,
            departmentId: isset($data['department_id']) ? (int) $data['department_id'] : null,
            type: $data['type'] ?? null,
            status: $data['status'] ?? null
        );
    }
}
