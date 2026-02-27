<?php

declare(strict_types=1);

namespace Src\Domain\Employee\Repositories;

use Src\Application\Shared\DTOs\PaginatedResult;
use Src\Application\Employee\DTOs\ListEmployeesCriteria;

interface EmployeeQueryRepositoryInterface
{
    public function findAllPaginated(ListEmployeesCriteria $criteria): PaginatedResult;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findActiveByOfficeId(int $officeId): array;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findDriversByCompanyId(int $companyId): array;
}
